<?php

declare(strict_types=1);

namespace App\Actions\Verify;

use App\Actions\Stages\StageSchemas;
use App\Contracts\ModelGateway;
use App\Contracts\VerifierRegistry;
use App\Enums\MatchStatus;
use App\Enums\Stage;
use App\Enums\VerifyCheckStatus;
use App\Exceptions\HadithCorpusUnavailable;
use App\Exceptions\ModelCallFailed;
use App\Models\VerifyCheck;
use App\Services\Model\ModelCallRecorder;
use App\Services\Quota\SpendCap;
use App\Support\Model\StagePrompt;
use App\Support\Verification\DomainPolicy;
use App\Support\Verification\EvidenceInput;
use App\Support\Verify\Finding;
use App\Support\Verify\NearestAyah;
use Illuminate\Support\Facades\Log;

/**
 * أداة «تحقّق» — T-181: نصٌّ حرّ ← شواهد ← تقرير.
 *
 * **لا جديد في الطبقتين، والجديدُ الباب.** الاستخراجُ هو المرحلة الثالثة
 * بتعليماتها من `PROMPT-PACK.md` حرفاً بحرف (CLAUDE.md §2 القاعدة الثانية)،
 * والتحقّقُ هو المحقّقان الحتميّان أنفسهما بلا نموذج (القاعدة الثالثة).
 * فما يقوله التقرير عن شاهدٍ هو ما كانت ستقوله شاشةُ المراجعة عنه في ملخّص.
 *
 * ★ **وسقفُ الإنفاق يُفحص قبل النداء** (القاعدة الخامسة)، هنا وفي المتحكّم:
 * هناك قبل وضع الطلب في الطابور، وهنا ثانيةً لأنّ الوقف قد يقع بينهما.
 */
final class RunVerifyCheck
{
    public function __construct(
        private readonly ModelGateway $gateway,
        private readonly VerifierRegistry $registry,
        private readonly ModelCallRecorder $recorder,
        private readonly SpendCap $spendCap,
    ) {}

    public function handle(VerifyCheck $check): VerifyCheck
    {
        if ($check->status->isSettled() || $check->isPurged()) {
            return $check;
        }

        if ($this->spendCap->isHalted()) {
            return $this->fail($check, 'spend_cap');
        }

        $check->forceFill(['status' => VerifyCheckStatus::Extracting])->save();

        try {
            $extracted = $this->extract($check);
        } catch (ModelCallFailed $exception) {
            Log::warning('verify.extract_failed', ['verify_check_id' => $check->id, 'code' => $exception->errorCode]);

            return $this->fail($check, $exception->errorCode);
        }

        $check->forceFill(['status' => VerifyCheckStatus::Verifying])->save();

        $findings = [];

        try {
            foreach ($extracted as $position => $raw) {
                $findings[] = $this->verifyOne($position + 1, $raw);
            }
        } catch (HadithCorpusUnavailable) {
            // ★ T-213: لا تقريرَ فيه «لم يُعثر عليه» عن بحثٍ لم يجرِ.
            return $this->fail($check, HadithCorpusUnavailable::CODE);
        }

        $check->forceFill([
            'status' => VerifyCheckStatus::Done,
            'report' => ['findings' => $findings, 'counts' => self::counts($findings)],
            'evidence_count' => count($findings),
            'completed_at' => now(),
        ])->save();

        return $check;
    }

    /**
     * المرحلة الثالثة على النصّ الملصوق — **بتعليماتها كما هي**.
     *
     * والنصُّ في `user` ملفوفاً في `<transcript>` كأيّ تفريغ (§12): ما يلصقه
     * غريبٌ قد يكون «تجاهل ما سبق»، وهو مادّةٌ تُعالَج لا أمرٌ يُطاع.
     *
     * @return list<array<string, mixed>>
     *
     * @throws ModelCallFailed
     */
    private function extract(VerifyCheck $check): array
    {
        $response = $this->recorder->bookingTo($check, fn () => $this->gateway->call(
            stage: Stage::ExtractingEvidence,
            messages: StagePrompt::messages(Stage::ExtractingEvidence, (string) $check->text, DomainPolicy::DEFAULT),
            schema: StageSchemas::for(Stage::ExtractingEvidence),
        ));

        $evidence = $response->decoded['evidence'] ?? [];

        return array_values(array_filter(
            is_array($evidence) ? $evidence : [],
            static fn (mixed $row): bool => is_array($row) && trim((string) ($row['raw_text'] ?? '')) !== '',
        ));
    }

    /** @param  array<string, mixed>  $raw */
    private function verifyOne(int $index, array $raw): array
    {
        $kind = (string) ($raw['kind'] ?? '');

        // **النوعُ الذي لا محقّق له لا يُحكم عليه** — عقد `VerifierRegistry`.
        // ويُقال «لا مصدر لنوعه» لا «لم نجده»: ذاك عجزٌ عن البحث لا نتيجتُه.
        $verifier = $this->registry->for(DomainPolicy::DEFAULT, $kind);

        $result = $verifier?->verify(new EvidenceInput(
            kind: $kind,
            rawText: (string) ($raw['raw_text'] ?? ''),
            claimedSource: self::nullable($raw['claimed_source'] ?? null),
            claimedNarrator: self::nullable($raw['claimed_narrator'] ?? null),
            claimedTakhrij: self::nullable($raw['claimed_takhrij'] ?? null),
        ));

        // ★ آيةٌ لم تطابق: أقربُ آيةٍ إليها للمقارنة وحدها — T-182، ولا يتغيّر بها الحكم.
        $nearest = $kind === 'ayah' && $result?->status === MatchStatus::None
            ? NearestAyah::find((string) ($raw['raw_text'] ?? ''))
            : null;

        return Finding::build($index, $raw, $result, $nearest);
    }

    /**
     * @param  list<array<string, mixed>>  $findings
     * @return array<string, int>
     */
    public static function counts(array $findings): array
    {
        $counts = [
            MatchStatus::Exact->value => 0,
            MatchStatus::Partial->value => 0,
            MatchStatus::None->value => 0,
            Finding::UNVERIFIABLE => 0,
        ];

        foreach ($findings as $finding) {
            $counts[$finding['verdict']] = ($counts[$finding['verdict']] ?? 0) + 1;
        }

        return $counts;
    }

    private function fail(VerifyCheck $check, string $code): VerifyCheck
    {
        $check->forceFill([
            'status' => VerifyCheckStatus::Failed,
            'error_code' => $code,
            'completed_at' => now(),
        ])->save();

        return $check;
    }

    private static function nullable(mixed $value): ?string
    {
        return is_string($value) && trim($value) !== '' ? $value : null;
    }
}
