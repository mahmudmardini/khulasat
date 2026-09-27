<?php

declare(strict_types=1);

namespace Bench;

/**
 * `bench/RESULTS.md` — the four columns T-00 asks for, and the prose itself.
 *
 * **والاستبعاد ليس عموداً في مقياس.** النموذج الذي مرّر موضوعاً أو صحّح
 * لفظاً من حفظه يُكتب مستبعَداً في أعلى التقرير، مهما كان نثره — معيار
 * القبول الحاكم في T-00.
 *
 * **ومخرجات المرحلة 5 تُرفق كاملةً** لأنّ جودة النثر لا يحكم عليها رقم:
 * يقرؤها المستخدم، ثمّ طالب علم قبل الاعتماد.
 */
final class Report
{
    /**
     * @param  list<array<string, mixed>>  $runs
     * @param  list<array<string, mixed>>  $lectures
     * @param  list<string>  $modelKeys
     */
    public static function render(array $runs, array $lectures, array $modelKeys): string
    {
        $date = date('Y-m-d');
        $out = [];

        $out[] = '# نتائج قياس النماذج — T-00';
        $out[] = '';
        $out[] = "تاريخ التشغيل: {$date} · الأسعار مُتحقَّقة في ".Catalog::PRICES_VERIFIED_ON;
        $out[] = '';
        $out[] = sprintf(
            'محاضرات: %d · نماذج: %d من %d مزوّدين · مراحل مقيسة: 2 و3 و5.',
            count($lectures),
            count($modelKeys),
            count(array_unique(array_map(static fn (string $k): string => Catalog::get($k)['provider'], $modelKeys))),
        );
        $out[] = '';

        // ── الاستبعاد أوّلاً ──────────────────────────────────────────
        $out[] = '## المستبعَدون';
        $out[] = '';

        $disqualified = self::disqualifiedModels($runs);

        if ($disqualified === []) {
            $out[] = 'لا مستبعَد. **ولا يعني هذا سلامةً**: يعني أنّ المدسوس في هذه';
            $out[] = 'المحاضرات الثلاث لم يكشف خللاً. زِد الشواهد أو بدّلها.';
        } else {
            $out[] = '**نموذجٌ يمرّر شاهداً موضوعاً أو يصحّح لفظاً من حفظه يُستبعَد،';
            $out[] = 'مهما كانت جودة نثره.** ولا يُعوَّض هذا بشيء.';
            $out[] = '';

            foreach ($disqualified as $model => $reasons) {
                $out[] = "- **{$model}** — ".implode(' · ', array_unique($reasons));
            }
        }

        $out[] = '';

        // ── الجدول ────────────────────────────────────────────────────
        $out[] = '## الجدول';
        $out[] = '';
        $out[] = '| النموذج | المحاضرة | المدسوس | الانضباط | الكلفة | التوكنز | زمن المرحلة 5 | توكن/كلمة |';
        $out[] = '|---|---|---|---|---|---|---|---|';

        foreach ($runs as $run) {
            // **ومن لم يكتمل تشغيلُه لم ينجُ** — T-62. فالحارسُ لم يرَ متناً
            // تامّاً يحكم عليه، و«نجا» على مخرَجٍ مبتور حكمٌ بلا بيّنة.
            $verdict = match (true) {
                $run['disqualified'] !== [] => '✗ مستبعَد',
                $run['errors'] !== [] => '— لم يكتمل',
                default => '✓ نجا',
            };
            $discipline = $run['json_ok'] ? 'JSON صالح' : '✗ خرجٌ غير مطابق';

            if ($run['errors'] !== []) {
                $discipline = '✗ '.implode(' · ', $run['errors']);
            }

            $out[] = sprintf(
                '| `%s` | %s | %s | %s | $%.4f | %s/%s | %.1f ث | %.2f |',
                $run['model'],
                $run['lecture'],
                $verdict,
                $discipline,
                $run['cost'],
                number_format((float) $run['in_tokens']),
                number_format((float) $run['out_tokens']),
                $run['writing_ms'] / 1000,
                $run['tokens_per_word'],
            );
        }

        $out[] = '';
        $out[] = '**الكلفة أعلاه للمراحل 2 و3 و5 وحدها** — والمراحل 1 و6 و7 تُضاف';
        $out[] = 'عليها في الملخّص الكامل.';
        $out[] = '';

        // ── التفصيل ───────────────────────────────────────────────────
        $out[] = '## ما وجده الكشف في كلّ تشغيل';
        $out[] = '';

        foreach ($runs as $run) {
            $out[] = "### `{$run['model']}` × {$run['lecture']}";
            $out[] = '';
            $out[] = '- شواهد مدسوسة نُقلت كما وردت: '.self::list($run['evidence_findings']['carried']);
            $out[] = '- صُحِّحت من الحفظ (إخفاق حاجب): '.self::list($run['evidence_findings']['corrected_from_memory']);
            $out[] = '- أُسقطت فلم تصل طبقة التحقّق: '.self::list($run['evidence_findings']['dropped']);
            $out[] = '- موضوعٌ ظهر في المتن ولم يصله (إخفاق حاجب): '.self::list($run['body_findings']['passed_fabricated']);
            $out[] = '- شواهد مثبَّتة غُيّر لفظها (إخفاق حاجب): '.self::list($run['body_findings']['altered_fixed']);
            $out[] = '- شواهد مثبَّتة لم تُنقل إلى المتن: '.self::list($run['body_findings']['dropped_fixed']);
            $out[] = '';
        }

        // ── النثر كاملاً ──────────────────────────────────────────────
        $out[] = '## مخرجات المرحلة 5 كاملةً';
        $out[] = '';
        $out[] = '**تُقرأ بالعين لا بالمقياس.** وجودة النثر لا تعوّض خللاً في';
        $out[] = 'الأمانة، فمن استُبعد أعلاه لا يُعاد النظر فيه لحسن عبارته.';
        $out[] = '';

        foreach ($runs as $run) {
            $out[] = "### `{$run['model']}` × {$run['lecture']}";
            $out[] = '';
            $out[] = '```json';
            $out[] = trim((string) $run['body_html']);
            $out[] = '```';
            $out[] = '';
        }

        // ── ما يبقى للمستخدم ─────────────────────────────────────────
        $out[] = '## التوصية';
        $out[] = '';
        $out[] = '_تُكتب هنا يدوياً بعد قراءة المخرجات._ ولا يحسمها السكربت:';
        $out[] = 'بندُ T-00 السادس يقول «اعرض النتائج على المستخدم ولا تحسم بنفسك»،';
        $out[] = 'ويذكّر بعرض مخرجات المرحلة 5 على طالب علم قبل الاعتماد.';
        $out[] = '';
        $out[] = 'وقيمُ `model_config` المعتمدة تُنقل بعدها إلى';
        $out[] = '`database/seeders/ModelConfigSeeder.php`، **بأسعارها معها**.';
        $out[] = '';

        return implode("\n", $out);
    }

    /**
     * @param  list<array<string, mixed>>  $runs
     * @return array<string, list<string>>
     */
    private static function disqualifiedModels(array $runs): array
    {
        $models = [];

        foreach ($runs as $run) {
            if ($run['disqualified'] === []) {
                continue;
            }

            $models[$run['model']] = array_merge($models[$run['model']] ?? [], $run['disqualified']);
        }

        return $models;
    }

    /** @param list<string> $items */
    private static function list(array $items): string
    {
        return $items === [] ? '—' : implode(' · ', $items);
    }
}
