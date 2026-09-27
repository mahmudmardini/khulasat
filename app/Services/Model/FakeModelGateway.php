<?php

declare(strict_types=1);

namespace App\Services\Model;

use App\Console\Commands\RecordModelResponse;
use App\Contracts\ModelGateway;
use App\Enums\Stage;
use App\Exceptions\ModelCallFailed;
use App\Models\ModelConfig;
use App\Models\SummaryJob;
use App\Support\Model\JsonSchema;
use App\Support\Model\ModelResponse;
use JsonException;

/**
 * Canned model responses read from disk — CLAUDE.md §2 القاعدة السابعة.
 *
 * **الافتراضي في `local` و`testing`**، ولا يُبدَّل إلا بـ `MODEL_GATEWAY=real`
 * صريحاً. والسبب مالٌ ووقت معاً: «كلّ عمل على T-11 وT-12 وT-14 وT-19
 * يستدعي نموذجاً. بلا هذا، كلّ تشغيل محلّي يحرق توكنز حقيقية، والاختبارات
 * تصير بطيئة وغير حتمية وتعتمد على الشبكة».
 *
 * ويقرأ من `tests/Fixtures/model-responses/<stage>/<case>.json`، ويُسجَّل
 * الملفّ بأمر {@see RecordModelResponse} من نداءٍ
 * حقيقيّ واحد.
 *
 * **ويتحقّق من المخطّط كما تفعل الحقيقية.** ولو تساهل لصارت الاختبارات
 * تمرّ على مخرَجٍ لا يمرّ في الإنتاج، وهو أسوأ من لا اختبار.
 *
 * @see khulasah-build-spec.md §6-أ «البوّابة الوهمية»
 */
class FakeModelGateway implements ModelGateway
{
    /** الحالة التي تُقرأ حين لا تُطلب حالةٌ بعينها. */
    public const DEFAULT_CASE = 'default';

    /** حالاتُ الفشل المحفوظة، وكلٌّ منها يرمي بدل أن يعيد نصّاً. */
    private const FAILURE_CASES = [
        'timeout' => ['ytdlp_timeout', true],
        'rate_limited' => ['rate_limited', true],
        'provider_error' => ['provider_error', true],
        'content_rejected' => ['content_rejected', false],
        'authentication_failed' => ['authentication_failed', false],
    ];

    /** الحالة المفروضة للاستدعاء التالي، لكل مرحلة. */
    private array $forced = [];

    /** @var list<array{stage: Stage, messages: array, schema: array|null}> */
    public array $calls = [];

    public function __construct(private readonly ModelCallRecorder $recorder) {}

    /** يفرض حالةً بعينها للمرحلة، فيُختبر مسارُ الفشل حتمياً. */
    public function willReturn(Stage $stage, string $case): void
    {
        $this->forced[$stage->value] = $case;
    }

    /**
     * @param  list<array{role: string, content: string}>  $messages
     * @param  array<string, mixed>|null  $schema
     *
     * @throws ModelCallFailed
     */
    public function call(
        Stage $stage,
        array $messages,
        ?array $schema = null,
        ?SummaryJob $job = null,
    ): ModelResponse {
        $this->calls[] = ['stage' => $stage, 'messages' => $messages, 'schema' => $schema];

        $case = $this->forced[$stage->value] ?? self::DEFAULT_CASE;
        unset($this->forced[$stage->value]);

        if (isset(self::FAILURE_CASES[$case])) {
            [$code, $retryable] = self::FAILURE_CASES[$case];

            throw $retryable
                ? ModelCallFailed::retryable($code, "حالة وهمية: {$case}.", $stage)
                : ModelCallFailed::permanent($code, "حالة وهمية: {$case}.", $stage);
        }

        /*
         * ★ **الترجمة تُصدي المفاتيح، ولا يصلح لها ملفٌّ ثابت** — T-38.
         *
         * وسائرُ المراحل تُقرأ من ردٍّ مسجَّل لأنّ «ردّاً مصنوعاً في الطيران
         * يجعل الاختبار يقيس خيالَنا». **وهذه نقيضُها**: عقدُها أن تردّ
         * مفتاحاً لكلّ مفتاحٍ وصلها، فردٌّ مسجَّل لا يصلح إلّا لمدخلٍ واحد
         * بعينه، ويُخفق في كلّ اختبارٍ سواه.
         *
         * فالإصداءُ هنا **هو** محاكاةُ العقد لا اختراعُ محتوى: يُثبت أنّ
         * المفاتيح تعود إلى مواضعها، وأنّ لفظ الشاهد لم يدخل الجدولَ أصلاً.
         */
        if ($stage === Stage::Translating) {
            $response = $this->echoTranslation($messages);

            $this->recorder->record($response, $job);

            return $response;
        }

        $content = $this->read($stage, $case);
        $decoded = null;

        if ($stage->expectsJson()) {
            $decoded = $this->decode($content);

            if ($decoded === null) {
                throw ModelCallFailed::schemaValidation('الردّ المسجَّل ليس JSON صالحاً.', $stage);
            }

            $violations = $schema === null ? [] : JsonSchema::violations($decoded, $schema);

            if ($violations !== []) {
                throw ModelCallFailed::schemaValidation(implode(' · ', array_slice($violations, 0, 5)), $stage);
            }
        }

        $response = $this->response($stage, $content, $decoded);

        $this->recorder->record($response, $job);

        return $response;
    }

    /**
     * يردّ لكلّ مفتاحٍ نصَّه مسبوقاً برمز اللغة — T-38.
     *
     * @param  list<array{role: string, content: string}>  $messages
     */
    private function echoTranslation(array $messages): ModelResponse
    {
        $payload = json_decode((string) (end($messages)['content'] ?? ''), true);

        $locale = is_array($payload) ? (string) ($payload['target_locale'] ?? 'xx') : 'xx';
        $strings = is_array($payload) && is_array($payload['strings'] ?? null) ? $payload['strings'] : [];

        $rows = [];

        foreach ($strings as $row) {
            if (is_array($row) && isset($row['key'], $row['text'])) {
                $rows[] = ['key' => (string) $row['key'], 'text' => "[{$locale}] ".$row['text']];
            }
        }

        $decoded = ['translations' => $rows];

        return new ModelResponse(
            stage: Stage::Translating,
            provider: 'fake',
            modelId: 'fake-translator',
            content: (string) json_encode($decoded, JSON_UNESCAPED_UNICODE),
            decoded: $decoded,
        );
    }

    /** مسار ملفّ حالةٍ ما — يُستعمل في التسجيل أيضاً. */
    public static function path(Stage $stage, string $case): string
    {
        return base_path("tests/Fixtures/model-responses/{$stage->value}/{$case}.json");
    }

    /** @throws ModelCallFailed */
    private function read(Stage $stage, string $case): string
    {
        $path = self::path($stage, $case);

        if (! is_file($path)) {
            // **لا يُخترع ردّ.** ردٌّ مصنوع في الطيران يجعل الاختبار يقيس
            // خيالَنا لا مخرَج النموذج، ويُخفي أنّ العيّنة ناقصة.
            throw ModelCallFailed::permanent(
                'fixture_missing',
                "لا ردّ مسجَّل للمرحلة {$stage->value} بالحالة {$case}. سجّله بـ khulasah:record-response.",
                $stage,
            );
        }

        $raw = (string) file_get_contents($path);

        try {
            $envelope = json_decode($raw, associative: true, flags: JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw ModelCallFailed::permanent('fixture_broken', "ملفّ الردّ {$path} معطوب.", $stage, $exception);
        }

        return is_array($envelope) && array_key_exists('content', $envelope)
            ? (string) $envelope['content']
            : $raw;
    }

    /** @return array<string, mixed>|list<mixed>|null */
    private function decode(string $content): ?array
    {
        try {
            $decoded = json_decode(trim($content), associative: true, flags: JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            return null;
        }

        return is_array($decoded) ? $decoded : null;
    }

    /**
     * تُقرأ الأسعار من `model_config` إن وُجدت، فتكون الكلفة الوهمية واقعية.
     *
     * @param  array<string, mixed>|list<mixed>|null  $decoded
     */
    private function response(Stage $stage, string $content, ?array $decoded): ModelResponse
    {
        $config = ModelConfig::forStage($stage);

        // تقديرٌ خشن: أربعة محارف للتوكن. يكفي ليكون للكلفة أثرٌ يُختبر،
        // ولا يُدّعى أنّه دقيق.
        $inputTokens = 1_000;
        $outputTokens = (int) max(1, mb_strlen($content) / 4);

        return new ModelResponse(
            stage: $stage,
            provider: $config?->provider ?? 'fake',
            modelId: $config?->model_id ?? 'fake-model',
            content: $content,
            inputTokens: $inputTokens,
            outputTokens: $outputTokens,
            costUsd: $config?->costFor($inputTokens, $outputTokens) ?? 0.0,
            durationMs: 0,
            attempts: 1,
            decoded: $decoded,
        );
    }
}
