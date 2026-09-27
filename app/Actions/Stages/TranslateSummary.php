<?php

declare(strict_types=1);

namespace App\Actions\Stages;

use App\Enums\Locale;
use App\Enums\Stage;
use App\Models\SummaryJob;
use App\Models\SummaryTranslation;
use App\Support\I18n\BodyStrings;
use App\Support\Quran\AyahTranslations;
use App\Support\Render\BodyBlocks;

/**
 * يترجم ملخّصاً إلى لغة — T-38.
 *
 * **تجري بعد الخطّ لا فيه.** التحقّق جرى على العربيّ مرّةً واحدة، وهذه
 * تنقل ما تحقّق ولا تُعيد تحقّقاً — معيارُ قبولٍ في T-38.
 *
 * ★★ **وثلاثةُ حدودٍ لا تُتجاوز، وكلُّها في الكود لا في التعليمات:**
 *
 * ١. **لفظُ الشاهد لا يُرسَل ولا يعود.** {@see BodyStrings} لا تضعه في
 *    الجدول أصلاً، فلا يبلغ النموذجَ. وتعليماتٌ تقول «لا تترجم الشاهد»
 *    رجاءٌ، وجدولٌ لا يحويه حدٌّ.
 * ٢. **الآيةُ لا يترجمها نموذج.** ترجمتُها معتمدةٌ تُقرأ من
 *    `quran_translations` عند العرض — ولا تمرّ من هنا.
 * ٣. **معنى الحديث يُترجَم موسوماً**، ويُعرض بجانب اللفظ العربي لا مكانه:
 *    «من قرأ الترجمة وحدها ظنّها الحديث».
 */
final class TranslateSummary
{
    use RunsAStage;

    /**
     * @param  bool  $force  يُجدّد الترجمة ولو طابقت البصمة — T-141.
     */
    public function handle(SummaryJob $job, Locale $locale, bool $force = false): SummaryTranslation
    {
        if ($locale->isSource()) {
            throw new \InvalidArgumentException('لغةُ المصدر لا تُترجَم — هي الأصل.');
        }

        /** @var array<string, mixed>|null $body */
        $body = $job->body_json;

        $request = $this->request($job, $body);
        $hash = self::fingerprint($locale, $request);

        $stored = SummaryTranslation::query()
            ->where('summary_job_id', $job->id)
            ->where('locale', $locale->value)
            ->first();

        /*
         * ★★ **ولا نداءَ لما تُرجم ومصدرُه لم يتبدّل** — T-141.
         *
         * فزرُّ «حدّثْ المنشور» يمرّ من هنا لكلّ لغةٍ في كلّ ضغطة، وكان
         * يصرف مالاً على عملٍ محفوظٍ في الجدول.
         *
         * **والشرطُ تطابقُ البصمة لا وجودُ الصفّ**: ملخّصٌ أُعيدت كتابتُه
         * متنُه غيرُ متنِه، وترجمةٌ قديمةٌ تُعرض له أسوأ من نداءٍ يُدفع.
         * وصفٌّ بلا بصمة (من قبل T-141) يُترجَم مرّةً أخيرةً ثمّ يستقرّ.
         */
        if (! $force
            && $stored !== null
            && $stored->source_hash === $hash
            && $stored->body_json !== null
        ) {
            return $this->rebuild($job, $locale, $stored);
        }

        if ($request === []) {
            return $this->store($job, $locale, $body, [], null, null, $hash);
        }

        $returned = $this->translate($job, $locale, $request);

        // ما عاد بمفتاح معنًى يُفرَز عن ترجمة المتن.
        $meanings = [];
        $plain = [];

        foreach ($returned as $key => $text) {
            if (str_starts_with($key, 'meaning:')) {
                $meanings[substr($key, strlen('meaning:'))] = $text;

                continue;
            }

            $plain[$key] = $text;
        }

        return $this->store(
            $job,
            $locale,
            BodyStrings::apply($body, $plain),
            $meanings,
            $plain['majlis.title'] ?? null,
            $plain['majlis.subtitle'] ?? null,
            $hash,
        );
    }

    /**
     * النصوصُ التي تُرسَل إلى النموذج — مصدرُ البصمة ومصدرُ النداء معاً.
     *
     * **واستُخرجت لتُحسَب البصمةُ من النصوص نفسِها لا من غيرها** (T-141):
     * بصمةُ `body_json` كلِّه تشمل مفاتيحَ عرضٍ وأصنافَ كتلٍ لا تُترجَم،
     * فتبدُّلُها يُوجب نداءً مدفوعاً بلا سبب.
     *
     * @param  array<string, mixed>|null  $body
     * @return array<string, string>
     */
    private function request(SummaryJob $job, ?array $body): array
    {
        // مفاتيحُ المعاني تُميَّز ببادئة، فيعود كلُّ نصٍّ إلى بابه.
        $request = BodyStrings::extract($body) + $this->majlisStrings($job);

        foreach (BodyStrings::hadithTexts($body) as $path => $text) {
            $request['meaning:'.$path] = $text;
        }

        return $request;
    }

    /**
     * بصمةُ ما يُرسَل — واللغةُ جزءٌ منها: النصُّ الواحد يُترجَم ترجمتين.
     *
     * **والمفاتيحُ تُرتَّب قبل البصم**: `BodyStrings::extract` تمرّ على
     * الشجرة، وترتيبُ مفاتيحها تفصيلُ تنفيذٍ لا معنًى — فبصمةٌ تتبعه تتبدّل
     * بلا تبدّل نصٍّ، فتوجب نداءً مدفوعاً.
     *
     * @param  array<string, string>  $request
     */
    private static function fingerprint(Locale $locale, array $request): string
    {
        ksort($request);

        return hash('sha256', $locale->value.'|'.json_encode($request, JSON_UNESCAPED_UNICODE));
    }

    /**
     * إعادةُ بناء `body_html` من الترجمة المحفوظة — **بلا نداءٍ ولا فلس**.
     *
     * ★★ **وهي إصلاحُ ضررٍ واقع، لا احتياطاً.**
     *
     * فـ{@see AyahTranslations::apply} تقرأ `quran_translations`، وتسقط
     * صامتةً إن كان فارغاً. وبيئةٌ لم يُبذَر فيها الجدولُ **تمحو ترجماتِ
     * الآيات من ملخّصٍ كان يحملها** عند أوّل إعادة نشر — وهو ما وقع على
     * الخادم في ١٦ أيلول ٢٠٢٦.
     *
     * وكلُّ ما تحتاجه محفوظٌ في الصفّ: `body_json` المترجَم و`meanings`.
     * فمن بذر الجدولَ بعد الترجمة تعود آياتُه بإعادة حسابٍ محضة.
     */
    public function rebuild(SummaryJob $job, Locale $locale, SummaryTranslation $stored): SummaryTranslation
    {
        /** @var array<string, mixed>|null $body */
        $body = $stored->body_json;
        /** @var array<string, string> $meanings */
        $meanings = (array) ($stored->meanings ?? []);

        $stored->forceFill([
            'body_html' => BodyBlocks::toHtml(
                AyahTranslations::apply(BodyStrings::withMeanings($body, $meanings), $job, $locale),
                $locale,
            ),
        ])->save();

        return $stored;
    }

    /**
     * عنوانُ الملخّص وعنوانُه الفرعي — يُترجمان مع المتن في نداءٍ واحد.
     *
     * **ونداءٌ واحد لا نداءان**: كلُّ نداءٍ يصرف مالاً، والعنوانُ سطران.
     *
     * @return array<string, string>
     */
    private function majlisStrings(SummaryJob $job): array
    {
        $structure = (array) ($job->structure_json ?? []);

        $out = [];

        foreach (['title' => 'title_ar', 'subtitle' => 'subtitle_ar'] as $key => $field) {
            $value = trim((string) ($structure[$field] ?? ''));

            if ($value !== '') {
                $out['majlis.'.$key] = $value;
            }
        }

        return $out;
    }

    /**
     * @param  array<string, string>  $request
     * @return array<string, string>
     */
    private function translate(SummaryJob $job, Locale $locale, array $request): array
    {
        $payload = json_encode([
            'target_language' => $locale->nativeName(),
            'target_locale' => $locale->value,
            'strings' => array_map(
                static fn (string $key, string $text): array => ['key' => $key, 'text' => $text],
                array_keys($request),
                array_values($request),
            ),
        ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

        $decoded = $this->runStage(Stage::Translating, (string) $payload, $job)->decoded;

        $rows = is_array($decoded['translations'] ?? null) ? $decoded['translations'] : [];

        $out = [];

        foreach ($rows as $row) {
            if (! is_array($row)) {
                continue;
            }

            $key = (string) ($row['key'] ?? '');
            $text = trim((string) ($row['text'] ?? ''));

            /*
             * **مفتاحٌ لم يُرسَل يُهمَل.** فنموذجٌ اخترع مفتاحاً يكتب في موضعٍ
             * لم يُطلب منه، وقد يكون موضعَ شاهد. والجدولُ المرسَل هو العقد.
             */
            if ($key !== '' && $text !== '' && array_key_exists($key, $request)) {
                $out[$key] = $text;
            }
        }

        return $out;
    }

    /**
     * @param  array<string, mixed>|null  $body
     * @param  array<string, string>  $meanings
     */
    private function store(
        SummaryJob $job,
        Locale $locale,
        ?array $body,
        array $meanings,
        ?string $title,
        ?string $subtitle,
        string $sourceHash,
    ): SummaryTranslation {
        return SummaryTranslation::query()->updateOrCreate(
            ['summary_job_id' => $job->id, 'locale' => $locale->value],
            [
                'tenant_id' => $job->tenant_id,
                // بصمةُ ما تُرجم، فلا يُعاد نداؤه ما لم يتبدّل — T-141.
                'source_hash' => $sourceHash,
                'title' => $title,
                'subtitle' => $subtitle,
                'body_json' => $body,
                /*
                 * يُبنى المتن هنا بالصانع نفسه، فالأصنافُ واحدةٌ في اللغات كلّها.
                 *
                 * ★ **والمعاني تُحقن في الشجرة قبل الرسم** — T-67. وكانت
                 * تُحفظ في `meanings` ولا يقرؤها أحد: **عملٌ يُنجَز ويُدفع
                 * ثمنُه ثمّ لا يُعرَض**، فتخرج الصفحةُ غير العربية بحديثٍ
                 * عربيٍّ عارٍ لا يفهمه قارئُها.
                 */
                /*
                 * ★ **وترجماتُ الآيات كذلك** — T-80. تُقرأ من الجدول المبذور
                 * لا من النموذج، وكانت مبذورةً لا يعرضها أحد.
                 */
                'body_html' => BodyBlocks::toHtml(
                    AyahTranslations::apply(BodyStrings::withMeanings($body, $meanings), $job, $locale),
                    $locale,
                ),
                'meanings' => $meanings,
            ],
        );
    }
}
