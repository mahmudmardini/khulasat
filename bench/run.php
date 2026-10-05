<?php

declare(strict_types=1);

/**
 * T-00 · قياس النماذج على مادّة حقيقية.
 *
 *   php bench/run.php --plan                 يعرض ما سيجري ولا يُنفق ريالاً
 *   php bench/run.php --live                 يشغّل القياس فعلاً (يصرف مالاً)
 *   php bench/run.php --live --models=opus-5.5,sonnet-5.5
 *
 * **لا يعمل بلا `--live` صريحة.** المفاتيح من البيئة، والنداءات حقيقية،
 * والحساب يُدفع. فليس هذا سكربتاً يُشغَّل بالسهو.
 *
 * @see TASKS.md T-00 · bench/README.md
 */

use App\Support\Model\TranscriptEnvelope;
use Bench\Catalog;
use Bench\Detector;
use Bench\PromptPack;
use Bench\Providers;
use Bench\Report;

require __DIR__.'/../vendor/autoload.php';
require __DIR__.'/lib/Catalog.php';
require __DIR__.'/lib/Detector.php';
require __DIR__.'/lib/PromptPack.php';
require __DIR__.'/lib/Providers.php';
require __DIR__.'/lib/Report.php';

$options = getopt('', ['live', 'plan', 'models::', 'lectures::', 'out::']);
$live = isset($options['live']);
$modelKeys = isset($options['models']) && $options['models'] !== ''
    ? array_map('trim', explode(',', (string) $options['models']))
    : Catalog::shortlist();
$lectureDir = (string) ($options['lectures'] ?? __DIR__.'/lectures');
$outPath = (string) ($options['out'] ?? __DIR__.'/RESULTS.md');

$pack = PromptPack::fromFile(__DIR__.'/../prompts/islamic/PROMPT-PACK.md');
$lectures = loadLectures($lectureDir);

echo 'قياس T-00 — الأسعار مُتحقَّقة في '.Catalog::PRICES_VERIFIED_ON."\n";
echo str_repeat('─', 64)."\n";
printf("المحاضرات: %d · النماذج: %s\n", count($lectures), implode(' · ', $modelKeys));

foreach ($modelKeys as $key) {
    $model = Catalog::get($key);
    $configured = Providers::keyFor($model['provider']) !== null;
    printf(
        "  %-16s %-10s %-26s %s\n",
        $key,
        $model['provider'],
        $model['model'],
        $configured ? 'المفتاح مضبوط' : '⚠ لا مفتاح — يُتخطّى'
    );
}

$providers = array_unique(array_map(static fn (string $k): string => Catalog::get($k)['provider'], $modelKeys));

if (count($modelKeys) < 4 || count($providers) < 3) {
    echo "\n⚠ معيار القبول: أربعة نماذج على الأقلّ من ثلاثة مزوّدين. "
        .'والحالي {'.count($modelKeys).'} من {'.count($providers)."}.\n";
}

if ($lectures === []) {
    echo "\n✗ لا محاضرات في {$lectureDir}. اقرأ bench/README.md.\n";
    exit(1);
}

if (! $live) {
    echo "\nهذا عرضٌ فقط. أضف --live لتشغيل النداءات الحقيقية.\n";
    echo 'وستُنادى '.(count($lectures) * count($modelKeys) * 3)." مرّة (٣ مراحل لكلّ نموذج ومحاضرة).\n";
    exit(0);
}

$runs = [];

foreach ($lectures as $lecture) {
    foreach ($modelKeys as $key) {
        $model = Catalog::get($key);

        if (Providers::keyFor($model['provider']) === null) {
            continue;
        }

        echo "\n▸ {$lecture['slug']} × {$key}\n";
        $runs[] = runOne($pack, $lecture, $key);
    }
}

file_put_contents($outPath, Report::render($runs, $lectures, $modelKeys));
echo "\n✓ كُتب التقرير: {$outPath}\n";
echo "اقرأ مخرجات المرحلة 5 بنفسك، واعرضها على طالب علم قبل اعتماد أيّ صفّ في model_config.\n";

/**
 * محاضرة واحدة على نموذج واحد: المراحل 2 و3 و5.
 *
 * @param  array<string, mixed>  $lecture
 * @return array<string, mixed>
 */
function runOne(PromptPack $pack, array $lecture, string $modelKey): array
{
    $transcript = $lecture['transcript'];
    // **غلافُ المنتج نفسه** لا نسخةٌ منه — فإن تبدّل هناك تبدّل هنا.
    $envelope = TranscriptEnvelope::wrap($transcript);
    $words = count(preg_split('/\s+/u', $transcript, -1, PREG_SPLIT_NO_EMPTY) ?: []);

    // المرحلة 2 — استخراج البنية.
    echo '  المرحلة 2… ';
    $structure = Providers::call($modelKey, $pack->stage(2), $envelope, 16_000, 600);
    echo report($structure);

    // المرحلة 3 — استخراج الشواهد. **وهي موضع المعيار الحاجب.**
    //
    // والمهلة ٦٠٠ لا ١٨٠ — T-62: أسقطت ١٨٠ `gpt-5.6-sol` بـ«صفر بايت»، أي
    // أنّه كان يفكّر ولم يبدأ الردّ. والسقف ١٦٬٠٠٠: بلغ Opus فيها ٧٬٨١٨ من ٨٬٠٠٠.
    echo '  المرحلة 3… ';
    $evidenceCall = Providers::call($modelKey, $pack->stage(3), $envelope, 16_000, 600);
    echo report($evidenceCall);

    $evidence = decodeJson($evidenceCall['text'])['evidence'] ?? [];
    $evidenceFindings = Detector::inspectEvidenceStage(is_array($evidence) ? $evidence : [], $lecture['planted']);

    // المرحلة 5 — كتابة المتن، **بالشواهد بعد التحقّق**.
    //
    // والموضوعُ لا يصلها: طبقةُ التحقّق ترفعه للمراجعة ولا تُثبّته. فظهورُه
    // في المتن استحضارٌ من الحفظ لا نقلٌ عمّا وصل — وهو الاستبعاد الثاني.
    //
    // ★ **وبمادّة `WriteBody` نفسها** — T-62: التفريغُ والبنيةُ والشواهد، في
    // الغلاف نفسه. وكانت بنيةً وشواهدَ فقط (بُني الحارسُ قبل T-52)، فقاست
    // النماذجَ بعدلٍ **ولم تمثّل الخطَّ كما صار**.
    $fixed = evidenceItems($lecture['verified_evidence']);
    $material = (string) json_encode([
        'transcript' => $transcript,
        'structure' => decodeJson($structure['text']),
        'evidence' => $fixed,
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

    // والسقف ٣٢٬٠٠٠ لا ١٦٬٠٠٠: التفريغُ يُطيل المتن، والتفكيرُ يُحسب من الخرج.
    echo '  المرحلة 5… ';
    $body = Providers::call($modelKey, $pack->stage(5), TranscriptEnvelope::wrap($material), 32_000, 900);
    echo report($body);

    $bodyFindings = Detector::inspectBodyStage($body['text'], $lecture['planted'], $fixed);

    $inTokens = $structure['in'] + $evidenceCall['in'] + $body['in'];
    $outTokens = $structure['out'] + $evidenceCall['out'] + $body['out'];

    return [
        'lecture' => $lecture['slug'],
        'model' => $modelKey,
        'provider' => Catalog::get($modelKey)['provider'],
        'errors' => array_values(array_filter([
            $structure['error'] === null ? null : "المرحلة 2: {$structure['error']}",
            $evidenceCall['error'] === null ? null : "المرحلة 3: {$evidenceCall['error']}",
            $body['error'] === null ? null : "المرحلة 5: {$body['error']}",
        ])),
        // الانضباط: أخرجت المرحلتان 2 و3 JSON صالحاً أم لا.
        'json_ok' => decodeJson($structure['text']) !== null && decodeJson($evidenceCall['text']) !== null,
        'evidence_findings' => $evidenceFindings,
        'body_findings' => $bodyFindings,
        'disqualified' => Detector::disqualifications($evidenceFindings, $bodyFindings),
        'in_tokens' => $inTokens,
        'out_tokens' => $outTokens,
        'cost' => Catalog::cost($modelKey, $inTokens, $outTokens),
        'ms' => $structure['ms'] + $evidenceCall['ms'] + $body['ms'],
        'writing_ms' => $body['ms'],
        // **معامل الترميز العربي مقيساً** — سؤالٌ لم يُجب عنه رقمٌ منشور.
        'tokens_per_word' => $words > 0 ? round($structure['in'] / $words, 3) : 0.0,
        'body_html' => $body['text'],
    ];
}

/**
 * الشواهد المثبَّتة بشكل `WriteBody::settledEvidence()` — T-62.
 *
 * وملفُّ المحاضرة يقبل النصَّ وحده (الشكلَ القديم) أو العنصرَ بحقوله. والنوعُ
 * حين لا يُذكر يُستدلّ عليه بقوس الآية، **ولا يُخترع تخريجٌ لم يُكتب**.
 *
 * @param  list<string|array<string, mixed>>  $evidence
 * @return list<array<string, string>>
 */
function evidenceItems(array $evidence): array
{
    return array_values(array_map(static function (string|array $item): array {
        if (is_string($item)) {
            return ['kind' => str_starts_with(trim($item), '﴿') ? 'ayah' : 'hadith', 'text' => $item];
        }

        return array_filter([
            'kind' => (string) ($item['kind'] ?? 'hadith'),
            'text' => (string) ($item['text'] ?? ''),
            'takhrij' => isset($item['takhrij']) ? (string) $item['takhrij'] : null,
            'grade' => isset($item['grade']) ? (string) $item['grade'] : null,
        ], static fn (?string $value): bool => $value !== null);
    }, $evidence));
}

/** @param array{error: ?string, ms: int, in: int, out: int} $call */
function report(array $call): string
{
    return $call['error'] !== null
        ? "✗ {$call['error']}\n"
        : sprintf("✓ %d/%d توكن · %.1f ث\n", $call['in'], $call['out'], $call['ms'] / 1000);
}

/** @return array<string, mixed>|null */
function decodeJson(string $text): ?array
{
    $text = trim($text);

    // النموذج قد يسوّر خرجه بأسوار كود رغم النهي، وهذا انضباطٌ ناقص لا
    // إخفاق مخطّط. يُسجَّل ويُقشَّر.
    if (preg_match('/```(?:json)?\s*\n(.*?)\n```/s', $text, $matches) === 1) {
        $text = $matches[1];
    }

    $decoded = json_decode($text, true);

    return is_array($decoded) ? $decoded : null;
}

/**
 * كلّ محاضرة: نصٌّ مفرَّغ، وملفّ يصف ما دُسّ فيه.
 *
 * @return list<array<string, mixed>>
 */
function loadLectures(string $directory): array
{
    $lectures = [];

    foreach (glob(rtrim($directory, '/').'/*.json') ?: [] as $manifestPath) {
        if (str_ends_with($manifestPath, 'EXAMPLE.json')) {
            continue;
        }

        $manifest = json_decode((string) file_get_contents($manifestPath), true);

        if (! is_array($manifest)) {
            fwrite(STDERR, "⚠ ملفّ وصفٍ معطوب: {$manifestPath}\n");

            continue;
        }

        $transcriptPath = dirname($manifestPath).'/'.($manifest['transcript'] ?? '');

        if (! is_file($transcriptPath)) {
            fwrite(STDERR, "⚠ تفريغٌ مفقود: {$transcriptPath}\n");

            continue;
        }

        $lectures[] = [
            'slug' => (string) ($manifest['slug'] ?? basename($manifestPath, '.json')),
            'transcript' => (string) file_get_contents($transcriptPath),
            'planted' => $manifest['planted'] ?? [],
            'verified_evidence' => $manifest['verified_evidence'] ?? [],
        ];
    }

    return $lectures;
}
