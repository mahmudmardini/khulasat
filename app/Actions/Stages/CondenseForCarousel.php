<?php

declare(strict_types=1);

namespace App\Actions\Stages;

use App\Enums\Stage;
use App\Exceptions\ModelCallFailed;
use App\Models\SummaryJob;
use App\Support\Render\ContentObject;
use App\Support\Render\RenderedEvidence;
use App\Support\Render\Slide;
use App\Support\Render\SlideDeck;
use App\Support\Verification\QuoteGuard;
use Illuminate\Support\Facades\Log;

/**
 * المرحلة ٧ — نصّ الكاروسيل. `prompts/islamic/PROMPT-PACK.md` والمواصفة §8-أ.
 *
 * **وتُستدعى عند طلب الكاروسيل وحده**، فلا حالةَ لها في آلة الحالات ولا
 * تنقل المهمّة — كشأن المرحلة ٦.
 *
 * **ولا تُعيد قراءة التفريغ.** تكثّف `ContentObject` وحده: البنية المستخرَجة،
 * والشواهد **بعد التحقّق بألفاظ مصادرها**. والتفريغ نصٌّ من عشرات الآلاف من
 * الكلمات، وإعادةُ إرساله لصنع عشر شرائح تصرف مالاً على معلومةٍ استُخرجت
 * سلفاً، وتفتح باب حقن التعليمات (§12) مرّةً ثانيةً بلا حاجة.
 */
final class CondenseForCarousel
{
    use RunsAStage;

    /** @throws ModelCallFailed */
    public function handle(SummaryJob $job, ?ContentObject $content = null): SlideDeck
    {
        $content ??= ContentObject::fromJob($job);

        if ($content->structure === []) {
            throw ModelCallFailed::permanent(
                'structure_missing',
                'لا بنية تُكثَّف إلى شرائح.',
                Stage::Carousel,
            );
        }

        $response = $this->runStage(Stage::Carousel, $this->material($content), $job);

        $deck = SlideDeck::fromModel((array) ($response->decoded ?? []));

        $this->guard($deck);

        // **التثبيت بعد الحراسة لا قبلها**: الحراسة تقيس ما أخرجه النموذج،
        // والتثبيت يُصلح ما أفسده. ولو قِيس بعد الإصلاح لمرّ الاختصار صامتاً.
        return $this->quotes($deck->anchoredTo($content), $content, $job);
    }

    /**
     * حارسُ الاقتباس في نصّ الشرائح الحرّ — T-160.
     *
     * البنيةُ تنقل ألفاظ الآيات والأحاديث **كما قالها المحاضر بلا تصحيح**
     * (المرحلة ٢)، والتكثيف يبني عليها. فشريحةُ مفهومٍ قد تحمل حديثاً بلفظٍ لم
     * يُتحقَّق منه. وشرائحُ اللفظ المصدريّ ثبّتها {@see SlideDeck::anchoredTo()}.
     *
     * **وشريحةٌ فرغ متنُها تسقط**، ويُعاد الترقيم. ولا يُعاد فحصُ العدد: شريحةٌ
     * أقلّ خيرٌ من شريحةٍ تنسب لفظاً لم يُتحقَّق منه.
     */
    private function quotes(SlideDeck $deck, ContentObject $content, SummaryJob $job): SlideDeck
    {
        $guard = QuoteGuard::for(array_map(
            static fn (RenderedEvidence $item): string => $item->text,
            $content->evidence,
        ));

        $kept = [];

        foreach ($deck->slides as $slide) {
            if ($slide->kind->carriesSourceWording()) {
                $kept[] = $slide->withIndex(count($kept) + 1);

                continue;
            }

            $body = $guard->clean($slide->body);

            if (trim($body) === '' && trim($slide->body) !== '') {
                continue;
            }

            $kept[] = $slide->withBody($body, $slide->sourceLine, $slide->anchored)->withIndex(count($kept) + 1);
        }

        if ($guard->dropped() !== []) {
            Log::warning('quoted_text_guard.dropped', [
                'summary_job_id' => $job->id,
                'output' => 'carousel',
                'sentences' => $guard->dropped(),
            ]);
        }

        return new SlideDeck($kept);
    }

    /**
     * المادّة كما تصل النموذج: «بنية المحاضرة، والشواهد بعد التحقّق بألفاظ
     * مصادرها» — نصّ المرحلة ٧.
     */
    private function material(ContentObject $content): string
    {
        return (string) json_encode([
            'structure' => $content->structure,
            'evidence' => array_map(static fn (RenderedEvidence $item): array => [
                'kind' => $item->kind,
                'text' => $item->text,
                'source_line' => $item->citation(),
            ], $content->evidence),
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    /**
     * قيدا المرحلة ٧ اللذان يُقاسان حتمياً: العدد، وسقف النصّ الحرّ.
     *
     * **ولا يُقتطع نصٌّ ليمرّ.** الاقتطاع الآليّ يقصّ الجملة في منتصفها
     * فيُخرج معنًى ناقصاً باسم الجهة، وهو أسوأ من شريحةٍ لم تُرسَم. والإخفاق
     * هنا `schema_validation_failed`: مخرَجٌ خالف عقدَه، ونداءُ التكثيف
     * وحدَه رخيصٌ يُعاد — ولا يمسّ الخطّ ولا الملخّص المنشور.
     *
     * @throws ModelCallFailed
     */
    private function guard(SlideDeck $deck): void
    {
        if ($deck->count() < SlideDeck::MIN || $deck->count() > SlideDeck::MAX) {
            throw ModelCallFailed::schemaValidation(
                "عدد الشرائح {$deck->count()}، والمطلوب من ".SlideDeck::MIN.' إلى '.SlideDeck::MAX.'.',
                Stage::Carousel,
            );
        }

        $overlong = $deck->overlong();

        if ($overlong !== []) {
            $numbers = implode('، ', array_map(static fn (Slide $s): string => $s->label(), $overlong));

            throw ModelCallFailed::schemaValidation(
                "الشرائح ({$numbers}) تجاوز نصّها الحرّ ".SlideDeck::MAX_WORDS.' كلمة.',
                Stage::Carousel,
            );
        }
    }
}
