<?php

declare(strict_types=1);

namespace App\Actions\Stages;

use App\Contracts\ModelGateway;
use App\Enums\Stage;
use App\Exceptions\ModelCallFailed;
use App\Models\SummaryJob;
use App\Support\Model\ModelResponse;
use App\Support\Model\StagePrompt;

/**
 * What every stage action does the same way — T-11.
 *
 * ثلاثة تُوحَّد هنا فلا تُنسخ خمس مرّات:
 *   ١. التعليمات من `resources/prompts/<مجال الجهة>/` **حرفاً بحرف**
 *      — CLAUDE.md §2 وT-02ب.
 *   ٢. محتوى المستخدم في `user` ملفوفاً في `<transcript>` — §12.
 *   ٣. المخطّط من {@see StageSchemas} ويُتحقّق منه في البوّابة.
 *
 * والكلفة تُقيَّد في البوّابة، فلا تُمرَّر ثانيةً إلى `TransitionJob`.
 */
trait RunsAStage
{
    public function __construct(protected readonly ModelGateway $gateway) {}

    /** @throws ModelCallFailed */
    protected function runStage(Stage $stage, string $userContent, SummaryJob $job): ModelResponse
    {
        return $this->gateway->call(
            stage: $stage,
            // **التعليمات من مجال الجهة** — T-02ب. والمجال يُقرأ من الجهة
            // لا يُفترض، فحزمةٌ ثانية تصير مجلّداً لا فرعاً في الشيفرة.
            messages: StagePrompt::messages($stage, $userContent, $job->tenant->domain),
            schema: StageSchemas::for($stage),
            job: $job,
        );
    }
}
