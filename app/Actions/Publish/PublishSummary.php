<?php

declare(strict_types=1);

namespace App\Actions\Publish;

use App\Actions\Summary\TransitionJob;
use App\Contracts\PublishStore;
use App\Domain\Summary\JobState;
use App\Enums\Locale;
use App\Jobs\GenerateShareCards;
use App\Models\Output;
use App\Models\SummaryJob;
use App\Support\Publish\Paths;
use App\Support\Publish\Slug;
use RuntimeException;

/**
 * ينشر مخرجات المهمّة إلى التخزين — المواصفة §9.
 *
 * **والصفحة وحدها تُنشر**، على `{slug}` ولكلّ لغةٍ تاليةٍ مقطعُها. والشرائح
 * وحزمةُ صورها **تُنزَّل ولا تُنشر** — T-204.
 *
 * وإعادة النشر تكتب فوق الملفّ نفسه وتُبطل الكاش — فالرابط الذي شاركه
 * الناس لا يتبدّل بتصحيحٍ في المتن.
 */
class PublishSummary
{
    public function __construct(
        private readonly PublishStore $store,
        private readonly TransitionJob $transition,
    ) {}

    /**
     * @param  array<string, string>  $rendered  محتوى كل نوع مخرَج، بمفتاح النوع.
     * @return array<string, string> الروابط العامّة بمفتاح النوع.
     */
    public function handle(SummaryJob $job, array $rendered): array
    {
        /*
         * **لا نشر عند `needs_review`** — CLAUDE.md §2 القاعدة الرابعة:
         * «لا إعداد يتجاوز هذا، ولا وضع تطوير». والحارس هنا لا في الواجهة،
         * لأنّ الواجهة تُلتفّ عليها والفعل يُنادى من الطابور أيضاً.
         */
        if ($job->state === JobState::NeedsReview || $job->pendingEvidenceCount() > 0) {
            throw new RuntimeException('لا يُنشر ملخّص فيه شاهدٌ لم يُحسم.');
        }

        $slug = $this->ensureSlug($job);
        $tenant = $job->tenant;

        if ($tenant === null) {
            throw new RuntimeException('لا جهة لهذه المهمّة.');
        }

        /*
         * **اللغةُ الأولى تُقرّر الجذر** — T-51، لا لغةُ المصدر. فمن نشر
         * بالإنجليزية وحدها فجذرُه إنجليزيّ، ولا يبقى جذرٌ فارغ ينتظر
         * عربيّةً لم تُطلب.
         */
        $primary = Locale::primaryOf(
            $job->lecture?->outputLocales() ?? $tenant->outputLocales(),
        );

        $urls = [];

        foreach ($job->outputs()->get() as $output) {
            /** @var Output $output */
            if (! $output->type->isPublished()) {
                continue;
            }

            /*
             * **المفتاح `نوع:لغة`** — T-38، ومخرَجٌ واحد لكل لغة. ويُقبل
             * المفتاح المجرَّد للعربية، فلا ينكسر مستدعٍ لم يُحدَّث بعد.
             */
            $key = $output->type->value.':'.$output->locale->value;

            $contents = $rendered[$key]
                ?? ($output->locale === $primary ? ($rendered[$output->type->value] ?? null) : null);

            if ($contents === null) {
                continue;
            }

            $path = Paths::forOutput($tenant->slug, $slug, $output->type, $output->locale, $primary);

            $url = $this->store->put($path, $contents, 'text/html; charset=UTF-8');

            $output->forceFill([
                'storage_path' => $path,
                'public_url' => $url,
                'rendered_at' => now(),
            ])->save();

            /*
             * ★ **ولغةُ المصدر تُردّ بمفتاحها المجرَّد** — نظير مسارها بلا
             * مقطع. فالعقد القائم `['page' => …]`، وتغييرُه إلى `page:ar`
             * يكسر كلَّ مستدعٍ من أجل لغةٍ لم تُطلب منه.
             */
            $urls[$output->locale === $primary ? $output->type->value : $key] = $url;
        }

        if ($urls === []) {
            throw new RuntimeException('لا مخرَج قابلاً للنشر في هذه المهمّة.');
        }

        /*
         * **والحذف السابق يُلغى عند إعادة النشر.** فمهمّةٌ أُزيلت ثمّ نُشرت
         * ثانيةً لا تبقى عليها شاهدةُ 410، وإلّا خُدمت 410 لصفحةٍ قائمة.
         */
        $job->forceFill(['published_at' => now(), 'unpublished_at' => null])->save();

        if ($job->state !== JobState::Published) {
            $this->transition->handle($job, JobState::Published);
        }

        /*
         * بطاقاتُ المشاركة — T-144. **بعد النشر وفي الطابور**: الالتقاطُ
         * ثوانٍ بمتصفّح، والنشرُ لا ينتظره. ولا تُطلب والملتقِطُ مُطفأ —
         * الرابطُ يُجيب ببطاقة المنصّة، فلا عملَ يُصفّ بلا ثمرة.
         */
        if (config('khulasah.share_card.capturer') === 'chrome') {
            GenerateShareCards::dispatch($job);
        }

        return $urls;
    }

    /**
     * `slug` يُشتقّ مرّةً ويثبت — §9.
     *
     * **ولا يُعاد اشتقاقه عند إعادة النشر**: الرابط شاركه الناس، وتبديلُه
     * لتصحيحٍ في العنوان يكسر كلّ إحالةٍ إليه. ومن غيّره ظنّاً أنّه يُحسّن
     * فقد كسر ما لا يملك إصلاحه.
     */
    private function ensureSlug(SummaryJob $job): string
    {
        if ($job->slug !== null && $job->slug !== '') {
            return $job->slug;
        }

        $title = $job->structure_json['title_ar']
            ?? $job->lecture?->title_ar
            ?? 'summary';

        $slug = Slug::unique($title, fn (string $candidate): bool => SummaryJob::query()
            ->where('tenant_id', $job->tenant_id)
            ->where('slug', $candidate)
            ->whereKeyNot($job->getKey())
            ->exists());

        $job->forceFill(['slug' => $slug])->save();

        return $slug;
    }
}
