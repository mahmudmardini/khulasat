<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Enums\Stage;
use App\Exceptions\ModelCallFailed;
use App\Services\Model\DatabaseModelGateway;
use App\Services\Model\FakeModelGateway;
use App\Support\Model\TranscriptEnvelope;
use Illuminate\Console\Command;

/**
 * Records one real model response as a fixture — T-10ج.
 *
 * **هذا الأمر وحده ينادي نموذجاً حقيقياً في التطوير**، وبيد إنسانٍ يشغّله
 * قاصداً. وما عداه يقرأ من {@see FakeModelGateway}.
 *
 * ويُستدعى مرّةً لكلّ حالة، فيبقى الردّ محفوظاً في المستودع تقرؤه
 * الاختبارات مجّاناً وحتميّاً.
 */
class RecordModelResponse extends Command
{
    protected $signature = 'khulasah:record-response
                            {stage : اسم المرحلة — cleaning أو extracting_structure …}
                            {case=default : اسم الحالة}
                            {--file= : ملفّ فيه محتوى المستخدم}
                            {--system= : تعليمات النظام}
                            {--force : يكتب فوق ردٍّ محفوظ}';

    protected $description = 'يسجّل ردّ نموذج حقيقي كعيّنة للاختبارات — T-10ج';

    public function handle(DatabaseModelGateway $gateway): int
    {
        $stage = Stage::tryFrom((string) $this->argument('stage'));

        if ($stage === null) {
            $this->components->error(sprintf(
                'مرحلة غير معروفة. المتاح: %s',
                implode(' · ', array_column(Stage::cases(), 'value')),
            ));

            return self::FAILURE;
        }

        $case = (string) $this->argument('case');
        $path = FakeModelGateway::path($stage, $case);

        if (is_file($path) && ! $this->option('force')) {
            $this->components->error("الردّ محفوظ سلفاً في {$path}. استعمل --force للكتابة فوقه.");

            return self::FAILURE;
        }

        $content = $this->userContent();

        if ($content === null) {
            return self::FAILURE;
        }

        $messages = array_values(array_filter([
            $this->option('system') ? ['role' => 'system', 'content' => (string) $this->option('system')] : null,
            // محتوى المستخدم في الغلاف دائماً، حتى في التسجيل — §12.
            TranscriptEnvelope::userMessage($content),
        ]));

        $this->components->info("ينادي النموذج الحقيقي للمرحلة {$stage->value}…");

        try {
            $response = $gateway->call($stage, $messages);
        } catch (ModelCallFailed $failure) {
            $this->components->error("أخفق النداء برمز {$failure->errorCode}: {$failure->getMessage()}");

            return self::FAILURE;
        }

        @mkdir(dirname($path), 0755, recursive: true);

        file_put_contents($path, (string) json_encode([
            'stage' => $stage->value,
            'case' => $case,
            'provider' => $response->provider,
            'model_id' => $response->modelId,
            'recorded_at' => now()->toIso8601String(),
            'input_tokens' => $response->inputTokens,
            'output_tokens' => $response->outputTokens,
            'content' => $response->content,
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

        $this->components->info("حُفظ في {$path} — كلفة النداء {$response->costUsd}$.");

        return self::SUCCESS;
    }

    private function userContent(): ?string
    {
        $file = $this->option('file');

        if ($file === null) {
            $this->components->error('مرّر --file بملفّ فيه محتوى المستخدم.');

            return null;
        }

        if (! is_file((string) $file)) {
            $this->components->error("لا ملفّ على المسار {$file}.");

            return null;
        }

        return (string) file_get_contents((string) $file);
    }
}
