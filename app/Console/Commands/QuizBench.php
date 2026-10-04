<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Actions\Stages\BuildQuiz;
use App\Actions\Stages\StageSchemas;
use App\Enums\ReviewStatus;
use App\Enums\Stage;
use App\Exceptions\ModelCallFailed;
use App\Models\EvidenceItem;
use App\Models\ModelConfig;
use App\Models\SummaryJob;
use App\Services\Model\Drivers\AnthropicDriver;
use App\Services\Model\Drivers\GoogleDriver;
use App\Services\Model\Drivers\OpenAiDriver;
use App\Support\Model\JsonSchema;
use App\Support\Model\StagePrompt;
use App\Support\Quiz\QuizGuard;
use App\Support\Quiz\QuizQuestionDraft;
use App\Support\Render\RenderedEvidence;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use JsonException;

/**
 * قياسُ نماذج المرحلة ٨ — T-195، القرار ٦.
 *
 * **التعليماتُ والمادّةُ والحارسُ هي هي في المنتج**: `quiz.txt` بحرفه، و
 * {@see BuildQuiz::material()}، و{@see QuizGuard}. فما يُقاس هنا هو ما يُنشر.
 *
 * ★ **ولا ينادي بلا `--live`**، ويقف قبل أن يتجاوز `--max-usd` — النداءاتُ
 * تُدفع من الحساب (TASKS.md §0). **ولا يكتب في `model_calls`**: نداءُ قياسٍ
 * لا ينتمي إلى جهة، فلا يدخل فواتير الجهات ولا سقفَها.
 *
 * والمخرجاتُ في `storage/app/private/quiz-bench`، **لا في المستودع**: فيها
 * ألفاظُ دروس (قاعدة «لا بيانات»).
 */
class QuizBench extends Command
{
    protected $signature = 'khulasah:quiz-bench
                            {--input= : مجلّدٌ فيه دروسٌ بصيغة {structure, evidence}}
                            {--jobs= : أرقامُ ملخّصاتٍ قائمة، مفصولةٌ بفواصل}
                            {--models=sonnet-5.5,gemini-3.8-flash,gemini-3.7-flash}
                            {--runs=2 : تشغيلاتٌ لكلّ نموذجٍ على كلّ درس}
                            {--max-usd=2 : سقفُ الإنفاق — يقف قبل أن يتجاوزه}
                            {--out= : مجلّدُ المخرجات}
                            {--live : ينادي النماذج فعلاً}
                            {--rejudge= : يعيد حكم الحارس على مخرجاتٍ محفوظة بلا نداء}';

    protected $description = 'يقيس نماذج اختبار الفهم بالتعليمات والحارس نفسيهما — T-195';

    /**
     * الأسعارُ من صفحات المزوّدين الرسمية، **٤ أكتوبر ٢٠٢٦**. ومن قاس بعد شهرٍ
     * فليُعِد التحقّق — رقمٌ قديم يجعل عمود الكلفة كذباً مرتّباً.
     *
     * @var array<string, array{0: string, 1: string, 2: float, 3: float, 4: string}>
     */
    private const MODELS = [
        'sonnet-5.5' => ['anthropic', 'claude-sonnet-5-5', 2.0, 10.0, 'low'],
        'gemini-3.8-flash' => ['google', 'gemini-3.8-flash', 0.75, 3.75, 'low'],
        'gemini-3.7-flash' => ['google', 'gemini-3.7-flash', 0.75, 3.75, 'low'],
        'gemini-3.5-flash-lite' => ['google', 'gemini-3.5-flash-lite', 0.30, 2.50, 'low'],
        'gemini-3.1-flash-lite' => ['google', 'gemini-3.1-flash-lite', 0.25, 1.50, 'low'],
    ];

    /** تقديرُ النداء الواحد قبل التشغيل — رموزاً. ويُستبدل بالمقيس بعده. */
    private const ESTIMATE_IN = 9_000;

    private const ESTIMATE_OUT = 4_000;

    public function handle(): int
    {
        $lessons = $this->lessons();
        $models = array_values(array_filter(array_map('trim', explode(',', (string) $this->option('models')))));
        $runs = max(1, (int) $this->option('runs'));
        $cap = (float) $this->option('max-usd');

        foreach ($models as $key) {
            if (! isset(self::MODELS[$key])) {
                $this->components->error("نموذجٌ غير معروف: {$key}. المعروف: ".implode(' · ', array_keys(self::MODELS)));

                return self::FAILURE;
            }
        }

        if ($lessons === []) {
            $this->components->error('لا دروس. مرّر --input أو --jobs.');

            return self::FAILURE;
        }

        // **حارسٌ أُصلح لا يستوجب نداءً جديداً** — نظير `bench/rejudge.php` في T-62.
        if ($this->option('rejudge')) {
            return $this->rejudge((string) $this->option('rejudge'), $lessons);
        }

        $calls = count($lessons) * count($models) * $runs;
        $estimate = 0.0;
        foreach ($models as $key) {
            $estimate += $this->cost($key, self::ESTIMATE_IN, self::ESTIMATE_OUT) * count($lessons) * $runs;
        }

        $this->components->info(sprintf(
            '%d درساً × %d نماذج × %d تشغيل = %d نداءً. التقدير ≈ $%.3f، والسقف $%.2f.',
            count($lessons), count($models), $runs, $calls, $estimate, $cap,
        ));

        if (! $this->option('live')) {
            $this->components->warn('لم يُنادَ شيء. أعِد بـ --live لتشغيلٍ حقيقي.');

            return self::SUCCESS;
        }

        $out = (string) ($this->option('out') ?: storage_path('app/private/quiz-bench/'.now()->format('Ymd-His')));
        File::ensureDirectoryExists($out);

        $results = [];
        $spent = 0.0;

        foreach ($lessons as $name => $lesson) {
            foreach ($models as $key) {
                for ($run = 1; $run <= $runs; $run++) {
                    // **يقف قبل أن يتجاوز السقف**، لا بعده: التقديرُ يُضاف قبل النداء.
                    if ($spent + $this->cost($key, self::ESTIMATE_IN, self::ESTIMATE_OUT) > $cap) {
                        $this->components->warn(sprintf('وقفتُ عند $%.4f قبل أن يتجاوز النداءُ التالي السقف.', $spent));

                        break 3;
                    }

                    $row = $this->measure($key, $name, $lesson, $run);
                    $spent += $row['cost'];
                    $results[] = $row;

                    File::put("{$out}/{$name}--{$key}--{$run}.json", (string) json_encode($row, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));

                    $this->line(sprintf(
                        '%-24s %-22s #%d  %s  أسئلة %d/%d  %.1f ث  $%.4f',
                        $name, $key, $run, $row['error'] === null ? 'تمّ' : 'أخفق', $row['kept'], $row['returned'], $row['seconds'], $row['cost'],
                    ));
                }
            }
        }

        File::put("{$out}/REPORT.md", $this->report($results, $spent));
        [$sheet, $key] = $this->blindSheet($results, $lessons);
        File::put("{$out}/REVIEW.md", $sheet);
        File::put("{$out}/REVIEW-KEY.json", (string) json_encode($key, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));

        $this->components->info(sprintf('أُنفق $%.4f. المخرجات في %s', $spent, $out));

        return self::SUCCESS;
    }

    /**
     * @param  array{structure: array<string, mixed>, evidence: list<array{kind: string, text: string, source_line: string}>}  $lesson
     * @return array<string, mixed>
     */
    private function measure(string $key, string $name, array $lesson, int $run): array
    {
        [$provider, $modelId, , , $effort] = self::MODELS[$key];

        $config = new ModelConfig([
            'stage' => Stage::Quiz->value,
            'provider' => $provider,
            'model_id' => $modelId,
            'max_tokens' => 8_000,
            'thinking_level' => $effort,
            'timeout_seconds' => 180,
            'max_retries' => 0,
            'on_exhausted' => 'fail',
        ]);

        $messages = StagePrompt::messages(Stage::Quiz, BuildQuiz::material($lesson['structure'], $lesson['evidence']), 'islamic');
        $row = ['lesson' => $name, 'model' => $key, 'run' => $run, 'error' => null, 'input_tokens' => 0, 'output_tokens' => 0,
            'seconds' => 0.0, 'cost' => 0.0, 'returned' => 0, 'kept' => 0, 'dropped' => [], 'schema' => [], 'questions' => [], 'raw' => ''];

        $started = microtime(true);

        try {
            $result = $this->driver($provider)->send($config, $messages);
        } catch (ModelCallFailed $failed) {
            $row['error'] = $failed->errorCode.': '.mb_substr($failed->getMessage(), 0, 300);
            $row['seconds'] = round(microtime(true) - $started, 1);

            return $row;
        }

        $row['seconds'] = round(microtime(true) - $started, 1);
        $row['input_tokens'] = $result->inputTokens;
        $row['output_tokens'] = $result->outputTokens;
        $row['cost'] = round($this->cost($key, $result->inputTokens, $result->outputTokens), 5);
        $row['raw'] = $result->content;

        return $this->judge($row, $name, $lesson);
    }

    /**
     * يحكم الحارسُ على الخرج المحفوظ في الصفّ — بعد النداء، أو عند إعادة الحكم.
     *
     * @param  array<string, mixed>  $row
     * @param  array{structure: array<string, mixed>, evidence: list<array{kind: string, text: string, source_line: string}>}  $lesson
     * @return array<string, mixed>
     */
    private function judge(array $row, string $name, array $lesson): array
    {
        $decoded = $this->decode((string) $row['raw']);

        if ($decoded === null) {
            $row['error'] = 'الخرج ليس JSON صالحاً.';

            return $row;
        }

        $row['error'] = null;
        $row['schema'] = JsonSchema::violations($decoded, (array) StageSchemas::for(Stage::Quiz));
        $row['returned'] = count((array) ($decoded['questions'] ?? []));

        $ids = [];
        foreach (array_keys($lesson['evidence']) as $n) {
            $ids['e'.($n + 1)] = $n + 1;
        }

        $guard = QuizGuard::for(
            axisCount: count((array) ($lesson['structure']['axes'] ?? [])),
            evidenceIds: $ids,
            settledTexts: array_column($lesson['evidence'], 'text'),
            seed: crc32($name) & 0x7FFFFFFF,
        );

        $kept = $guard->guard($decoded);
        $row['kept'] = count($kept);
        $row['dropped'] = $guard->dropped();
        $row['questions'] = array_map(static fn (QuizQuestionDraft $d): array => [
            'kind' => $d->kind,
            'level' => $d->level,
            'prompt' => $d->prompt,
            'options' => $d->options,
            'correct' => $d->correctIndex,
            'explanation' => $d->explanation,
            'axis' => $d->axisIndex,
        ], $kept);

        return $row;
    }

    /**
     * @param  array<string, array{structure: array<string, mixed>, evidence: list<array{kind: string, text: string, source_line: string}>}>  $lessons
     */
    private function rejudge(string $dir, array $lessons): int
    {
        $results = [];

        foreach (glob(rtrim($dir, '/').'/*--*--*.json') ?: [] as $file) {
            $row = json_decode((string) file_get_contents($file), true);

            if (! is_array($row) || ! isset($lessons[$row['lesson']])) {
                continue;
            }

            // نداءٌ أخفق قبل أن يصل خرجُه لا يُعاد حكمُه: لا خرجَ له.
            if ($row['raw'] !== '') {
                $row = $this->judge($row, $row['lesson'], $lessons[$row['lesson']]);
                File::put($file, (string) json_encode($row, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
            }

            $results[] = $row;
        }

        usort($results, static fn (array $a, array $b): int => [$a['lesson'], $a['model'], $a['run']] <=> [$b['lesson'], $b['model'], $b['run']]);

        $spent = array_sum(array_column($results, 'cost'));
        File::put("{$dir}/REPORT.md", $this->report($results, $spent));
        [$sheet, $key] = $this->blindSheet($results, $lessons);
        File::put("{$dir}/REVIEW.md", $sheet);
        File::put("{$dir}/REVIEW-KEY.json", (string) json_encode($key, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));

        $this->components->info('أُعيد الحكم على '.count($results).' مخرَجاً بلا نداء.');

        return self::SUCCESS;
    }

    /** @return array<string, array{structure: array<string, mixed>, evidence: list<array{kind: string, text: string, source_line: string}>}> */
    private function lessons(): array
    {
        $lessons = [];

        $dir = (string) $this->option('input');
        if ($dir !== '') {
            foreach (glob(rtrim($dir, '/').'/*.json') ?: [] as $file) {
                $data = json_decode((string) file_get_contents($file), true);
                if (is_array($data) && isset($data['structure'], $data['evidence'])) {
                    $lessons[basename($file, '.json')] = $data;
                }
            }
        }

        foreach (array_filter(array_map('intval', explode(',', (string) $this->option('jobs')))) as $id) {
            $job = SummaryJob::acrossTenants()->find($id);
            if ($job === null || $job->structure_json === null) {
                continue;
            }

            $lessons["job-{$id}"] = [
                'structure' => (array) $job->structure_json,
                'evidence' => EvidenceItem::acrossTenants()
                    ->where('summary_job_id', $id)
                    ->whereNot('review_status', ReviewStatus::Removed->value)
                    ->orderBy('id')
                    ->get()
                    ->map(static function (EvidenceItem $item): array {
                        $shown = RenderedEvidence::fromItem($item);

                        return ['kind' => $shown->kind, 'text' => $shown->text, 'source_line' => $shown->citation()];
                    })->all(),
            ];
        }

        return $lessons;
    }

    private function driver(string $provider): AnthropicDriver|GoogleDriver|OpenAiDriver
    {
        return match ($provider) {
            'anthropic' => app(AnthropicDriver::class),
            'google' => app(GoogleDriver::class),
            default => app(OpenAiDriver::class),
        };
    }

    private function cost(string $key, int $in, int $out): float
    {
        return $in * self::MODELS[$key][2] / 1_000_000 + $out * self::MODELS[$key][3] / 1_000_000;
    }

    /** @return array<string, mixed>|null */
    private function decode(string $content): ?array
    {
        $trimmed = preg_replace('/^```(?:json)?\s*|\s*```$/u', '', trim($content)) ?? $content;

        try {
            $decoded = json_decode($trimmed, true, flags: JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            return null;
        }

        return is_array($decoded) ? $decoded : null;
    }

    /** @param list<array<string, mixed>> $results */
    private function report(array $results, float $spent): string
    {
        $lines = [
            '# قياس نماذج اختبار الفهم — T-195',
            '',
            'الأسعار من صفحات المزوّدين الرسمية، ٤ أكتوبر ٢٠٢٦. والتعليمات `resources/prompts/islamic/quiz.txt` بحرفها، والحارس `QuizGuard` نفسه.',
            '',
            '| النموذج | نداءات | أخفق | أسئلةٌ وصلت | بقيت بعد الحارس | لفظٌ منسوب | خللُ مخطّط | الزمن (ث) | كلفة النداء | المجموع |',
            '|---|---|---|---|---|---|---|---|---|---|',
        ];

        $by = [];
        foreach ($results as $row) {
            $by[$row['model']][] = $row;
        }

        foreach ($by as $model => $rows) {
            $ok = array_values(array_filter($rows, static fn (array $r): bool => $r['error'] === null));
            $quotes = 0;
            foreach ($rows as $r) {
                foreach ($r['dropped'] as $d) {
                    $quotes += str_contains((string) $d['reason'], 'ينسب لفظاً') ? 1 : 0;
                }
            }

            $avg = static fn (string $field): float => $ok === [] ? 0.0 : array_sum(array_column($ok, $field)) / count($ok);

            $lines[] = sprintf(
                '| %s | %d | %d | %.1f | %.1f | %d | %d | %.1f | $%.4f | $%.4f |',
                $model, count($rows), count($rows) - count($ok), $avg('returned'), $avg('kept'), $quotes,
                count(array_filter($ok, static fn (array $r): bool => $r['schema'] !== [])),
                $avg('seconds'), $avg('cost'), array_sum(array_column($rows, 'cost')),
            );
        }

        $lines[] = '';
        $lines[] = sprintf('**المجموع المنفَق: $%.4f**', $spent);
        $lines[] = '';
        $lines[] = '## الأسئلة المحذوفة وسببها';
        $lines[] = '';

        foreach ($results as $row) {
            if ($row['error'] !== null) {
                $lines[] = "- {$row['lesson']} · {$row['model']} #{$row['run']}: **أخفق** — {$row['error']}";
            }
            foreach ($row['dropped'] as $d) {
                $lines[] = "- {$row['lesson']} · {$row['model']} #{$row['run']}: السؤال {$d['index']} — {$d['reason']}";
            }
        }

        return implode("\n", $lines)."\n";
    }

    /**
     * ورقةُ المراجعة العمياء: أسئلةُ التشغيل الأوّل من كلّ نموذج، مخلوطةً بلا
     * أسماء. والمفتاحُ في ملفٍّ مستقلّ يُفتح بعد الحكم.
     *
     * @param  list<array<string, mixed>>  $results
     * @param  array<string, array<string, mixed>>  $lessons
     * @return array{0: string, 1: array<string, string>}
     */
    private function blindSheet(array $results, array $lessons): array
    {
        $lines = ['# مراجعةٌ عمياء — أسئلةٌ من نماذج مختلفة بلا أسمائها', '',
            'لكلّ سؤال: (١) أصحيحٌ الجوابُ بحسب الدرس؟ (٢) أخالٍ من معلومةٍ من خارجه؟ (٣) أمعقولةٌ الخياراتُ الخاطئة؟ (٤) أسليمةٌ العربية؟ (٥) أيُنشر بلا تعديل؟', ''];
        $key = [];
        $n = 0;

        foreach ($lessons as $name => $lesson) {
            $pool = [];
            foreach ($results as $row) {
                if ($row['lesson'] === $name && $row['run'] === 1) {
                    foreach ($row['questions'] as $q) {
                        $pool[] = ['model' => $row['model'], 'q' => $q];
                    }
                }
            }

            mt_srand(crc32($name));
            shuffle($pool);
            mt_srand();

            $lines[] = '## '.($lesson['structure']['title_ar'] ?? $name);
            $lines[] = '';

            foreach ($pool as $item) {
                $n++;
                $id = sprintf('Q-%03d', $n);
                $key[$id] = $item['model'];
                $q = $item['q'];
                $axis = $lesson['structure']['axes'][$q['axis']]['name'] ?? '—';

                $lines[] = "### {$id} · {$q['kind']} · ".($q['level'] ?? '—')." · {$axis}";
                $lines[] = '';
                $lines[] = $q['prompt'];
                $lines[] = '';
                foreach ($q['options'] as $k => $option) {
                    $text = $option['text'] ?? ($lesson['evidence'][$option['evidence_item_id'] - 1]['text'] ?? '');
                    $lines[] = '- '.($k === $q['correct'] ? '**✓** ' : '').$text;
                }
                $lines[] = '';
                $lines[] = '> '.$q['explanation'];
                $lines[] = '';
                $lines[] = 'الحكم: صحيح ☐ · من الدرس ☐ · خيارات معقولة ☐ · عربية سليمة ☐ · يُنشر بلا تعديل ☐';
                $lines[] = '';
            }
        }

        return [implode("\n", $lines)."\n", $key];
    }
}
