<?php

declare(strict_types=1);

/**
 * يعيد الحكم على مخرَجات المرحلة 5 المحفوظة — T-62، **بلا نداءٍ واحد**.
 *
 *   php bench/rejudge.php                                 التقرير والمحاضرات الافتراضيّان
 *   php bench/rejudge.php --results=مسار --lectures=مسار
 *
 * فحكمٌ باطلٌ صدر عن حارسٍ معيب يُصحَّح من المحفوظ، **ولا يُصرف ريالٌ
 * لتصحيحه**. و`RESULTS.md` يحفظ مخرَج المرحلة 5 كاملاً؛ أمّا المرحلة 3 فلا
 * يحفظها، فحكمُها لا يُعاد هنا.
 */

use Bench\Detector;

require __DIR__.'/../vendor/autoload.php';
require __DIR__.'/lib/Detector.php';

$options = getopt('', ['results::', 'lectures::']);
$resultsPath = (string) ($options['results'] ?? __DIR__.'/RESULTS.md');
$lectureDir = rtrim((string) ($options['lectures'] ?? __DIR__.'/lectures'), '/');

$markdown = @file_get_contents($resultsPath);

if ($markdown === false) {
    fwrite(STDERR, "✗ تعذّرت قراءة {$resultsPath}\n");
    exit(1);
}

$section = strstr($markdown, '## مخرجات المرحلة 5 كاملةً') ?: '';
$section = preg_split('/^## (?!مخرجات)/mu', $section)[0] ?? '';

preg_match_all('/^### `([^`]+)` × (\S+)\s*\n+```[a-z]*\n(.*?)\n```/msu', $section, $runs, PREG_SET_ORDER);

if ($runs === []) {
    echo "لا مخرَجات للمرحلة 5 في {$resultsPath}.\n";
    exit(0);
}

echo "إعادة الحكم على {$resultsPath} — بلا نداء\n";
echo str_repeat('─', 64)."\n";

foreach ($runs as [, $model, $slug, $output]) {
    $manifest = json_decode((string) @file_get_contents("{$lectureDir}/{$slug}.json"), true);

    if (! is_array($manifest)) {
        echo "  {$model} × {$slug}: ⚠ لا ملفّ وصفٍ للمحاضرة، يُتخطّى\n";

        continue;
    }

    $findings = Detector::inspectBodyStage($output, $manifest['planted'] ?? [], $manifest['verified_evidence'] ?? []);
    $reasons = Detector::disqualifications(
        ['corrected_from_memory' => [], 'dropped' => [], 'carried' => []],
        $findings,
    );

    // والمبتورُ يُعرف هنا بأنّه JSON لا يُقرأ: سببُ التوقّف لم يُحفظ معه.
    $complete = Detector::bodyText($output) !== $output;

    $verdict = match (true) {
        $reasons !== [] => '✗ مستبعَد — '.implode(' · ', $reasons),
        ! $complete => '— لم يكتمل: JSON لا يُقرأ (مبتورٌ غالباً)',
        default => '✓ نجا',
    };

    printf(
        "  %-16s × %-18s غُيّر: %d · لم يُنقل: %d · موضوعٌ مُرِّر: %d\n      %s\n",
        $model,
        $slug,
        count($findings['altered_fixed']),
        count($findings['dropped_fixed']),
        count($findings['passed_fabricated']),
        $verdict,
    );
}
