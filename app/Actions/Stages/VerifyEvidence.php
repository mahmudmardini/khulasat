<?php

declare(strict_types=1);

namespace App\Actions\Stages;

use App\Actions\Summary\TransitionJob;
use App\Contracts\VerifierRegistry;
use App\Domain\Summary\JobState;
use App\Enums\ReviewStatus;
use App\Enums\UnverifiedPolicy;
use App\Models\EvidenceItem;
use App\Models\SummaryJob;
use App\Support\Arabic;
use App\Support\Verification\DomainPolicy;
use App\Support\Verification\VerificationResult;
use Illuminate\Support\Facades\DB;

/**
 * مرحلة `verifying`: تُصيّر `evidence_json` صفوفاً محكوماً عليها — المواصفة §5 و§7.
 *
 * وهذه المرحلة **الوحيدة في الخطّ بلا نموذج لغوي**. الشواهد استُخرجت بنموذج،
 * وأمّا الحكم على وجودها في المصادر فمطابقةٌ نصّية حتمية — CLAUDE.md §2
 * القاعدة الثالثة. ومن استعان هنا بنموذج فقد أخطأ الطبقة كلَّها.
 *
 * **والتفرّع بعدها مآلٌ من ثلاثة** (§7-5، سياسة البيان):
 *   - `Removed`    — ما جُهل مصدره. يُحذف صامتاً من المتن، **ويبقى صفُّه
 *                    مقروءاً لصاحب الجهة**. وحذفُه من المتن ليس إخفاءً عنه.
 *   - `AutoPassed` — عُرف مصدره، ووضعُ الجهة يسمح بنشره مقروناً ببيانه.
 *   - `Pending`    — عُرف مصدره، والجهة اختارت أن يراه إنسان.
 *
 * فإن بقي `Pending` واحد وقف الخطّ عند `needs_review`. وإلّا مضى إلى
 * `writing` مباشرةً.
 */
class VerifyEvidence
{
    public function __construct(
        private readonly VerifierRegistry $registry,
        private readonly TransitionJob $transition,
    ) {}

    /**
     * @return list<EvidenceItem>
     */
    public function handle(SummaryJob $job): array
    {
        /** @var list<array<string, mixed>> $extracted */
        $extracted = $job->evidence_json ?? [];

        $items = DB::transaction(fn (): array => $this->materialise($job, $extracted));

        /*
         * **درسٌ بلا شاهد درسٌ صحيح** — كما في `ExtractEvidence`. فالفراغ
         * يمضي إلى الكتابة ولا يقف، إذ ليس فيه ما يُراجَع.
         */
        $blocked = array_filter($items, static fn (EvidenceItem $item): bool => ! $item->isSettled());

        $this->transition->handle($job, $blocked === [] ? JobState::Writing : JobState::NeedsReview);

        return $items;
    }

    /**
     * @param  list<array<string, mixed>>  $extracted
     * @return list<EvidenceItem>
     */
    private function materialise(SummaryJob $job, array $extracted): array
    {
        /*
         * إعادة التحقّق تبدأ من صفحة بيضاء: صفوفُ محاولةٍ سابقة تُحذف، وإلّا
         * تضاعفت الشواهد عند كلّ إعادة توليد. **ولا تُحذف المحسومة بيد
         * إنسان** — تلك قرارٌ بشريّ لا يُلغيه تشغيلٌ آلي.
         */
        $job->evidenceItems()
            ->whereIn('review_status', [ReviewStatus::AutoPassed->value, ReviewStatus::Pending->value, ReviewStatus::Removed->value])
            ->whereNull('resolved_by')
            ->delete();

        $mode = $job->tenant?->on_unverified ?? UnverifiedPolicy::Disclose;

        $items = [];

        foreach ($extracted as $raw) {
            $items[] = $this->verifyOne($job, $raw, $mode);
        }

        return $items;
    }

    /**
     * @param  array<string, mixed>  $raw
     */
    private function verifyOne(SummaryJob $job, array $raw, UnverifiedPolicy $mode): EvidenceItem
    {
        $kind = (string) ($raw['kind'] ?? '');
        $rawText = (string) ($raw['raw_text'] ?? '');

        $item = new EvidenceItem([
            'summary_job_id' => $job->id,
            'tenant_id' => $job->tenant_id,
            'kind' => $kind,
            'raw_text' => $rawText,
            'normalized_text' => Arabic::normalize($rawText),
            'source_meta' => array_filter([
                'book' => $raw['claimed_source'] ?? null,
                'narrator' => $raw['claimed_narrator'] ?? null,
                'takhrij' => $raw['claimed_takhrij'] ?? null,
            ], static fn (mixed $value): bool => $value !== null),
        ]);

        // المجال يُنسخ من الجهة في `booted()`، ويُقرأ قبل الحفظ ليُحلّ به المحقّق.
        $item->domain ??= $job->tenant?->domain ?? DomainPolicy::DEFAULT;

        $result = $this->verify($item);

        $item->fill([
            'match_status' => $result->status,
            'matched_text' => $result->matchedText,
            'source_ref' => $result->sourceRef,
            /*
             * ما ادّعاه النموذج يُحفظ، **وما وجده المحقّق يعلوه**. فالتخريج
             * والدرجة من المصدر لا من النموذج — معيار القبول الثالث. ومن
             * قدّم دعوى النموذج نشر تخريجاً لم يُقرأ من كتاب.
             */
            'source_meta' => [...$item->source_meta, ...$result->sourceMeta],
        ]);

        $item->review_status = ReviewStatus::decide($result, $item->domainPolicy(), $mode);

        $item->save();

        return $item;
    }

    /**
     * **النوع الذي لا محقّق له لا يمرّ** — عقد `VerifierRegistry`.
     *
     * فيعود `none`، ومآلُه في سياسة البيان الحذف: ما جُهل مصدره لا يُنشر.
     * وعجزُنا عن التحقّق ليس شهادةً بالصحّة ولا حكماً بالوضع.
     */
    private function verify(EvidenceItem $item): VerificationResult
    {
        $verifier = $this->registry->for($item->domain, $item->kind);

        if ($verifier === null) {
            return VerificationResult::none();
        }

        return $verifier->verify($item->toVerificationInput());
    }
}
