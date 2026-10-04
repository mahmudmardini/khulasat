<?php

declare(strict_types=1);

namespace App\Http\Controllers\Public;

use App\Contracts\PublishStore;
use App\Enums\Locale;
use App\Enums\OutputType;
use App\Http\Controllers\Controller;
use App\Models\SummaryJob;
use App\Models\Tenant;
use App\Support\Publish\Paths;
use Illuminate\Http\Response;

/**
 * الصفحة المنشورة على رابطٍ نظيف — T-127.
 *
 * **حلٌّ بيني ريثما يُوصَل التخزين بـCDN حقيقي** (T-15، مؤجَّل بقرار
 * مالك المنتج). العرض يمرّ على Laravel الآن — خلافاً للمواصفة النهائية
 * §9 — والمسار المقروء هو نفسه ما يحسبه {@see Paths::forOutput()}
 * حرفاً بحرف، فتبديل الخادم لاحقاً لا يُغيّر رابطاً شاركه أحد.
 *
 * **و410 حقيقيّ لا نصّاً وحده**: `UnpublishSummary` علّل الاكتفاء بالنصّ
 * بأنّ «التخزين الساكن لا يردّ رمز حالةٍ من عنده» — وهذا العرض ليس تخزيناً
 * ساكناً، فلا مسوّغ لإسقاط الرمز الصحيح ما دام متاحاً بلا كلفة.
 */
final class ShowPublishedSummaryController extends Controller
{
    public function __construct(private readonly PublishStore $store) {}

    public function __invoke(string $tenantSlug, string $summarySlug, string $rest = ''): Response
    {
        $tenant = Tenant::query()->where('slug', $tenantSlug)->first();

        if ($tenant === null) {
            abort(404);
        }

        $parsed = $this->parseRest($rest);

        if ($parsed === null) {
            abort(404);
        }

        [$locale, $type] = $parsed;
        $isRootPage = $locale === null && $type === OutputType::Page;

        $job = SummaryJob::acrossTenants()
            ->where('tenant_id', $tenant->id)
            ->where('slug', $summarySlug)
            ->first();

        /*
         * **الصفّ قد يكون غائباً وهو منشورٌ فعلاً** — `DeleteSummary` يكتب
         * الشاهدة ثمّ يحذف الصفّ (المواصفة §9، وتعليلُها في الصفّ نفسه):
         * فلا يُعرف بعده متى نُشر ولا بأيّ لغة، **والجذر وحده يحمل شاهدةً**.
         */
        if ($job === null || $job->unpublished_at !== null) {
            if ($isRootPage) {
                return $this->tombstone($tenant->slug, $summarySlug);
            }

            abort(404);
        }

        if ($job->published_at === null) {
            abort(404);
        }

        $primary = Locale::primaryOf(
            $job->lecture?->outputLocales() ?? $tenant->outputLocales(),
        );

        $path = Paths::forOutput($tenant->slug, $summarySlug, $type, $locale, $primary);
        $contents = $this->store->get($path);

        if ($contents === null) {
            abort(404);
        }

        return response($contents, 200)->header('Content-Type', 'text/html; charset=UTF-8');
    }

    /**
     * يقرأ ما بعد `{tenantSlug}/{summarySlug}/` — صورتان فقط بالضبط كما
     * تكتبهما {@see Paths::forOutput()}: فارغة، أو `{locale}`.
     *
     * **ولا `carousel` بعد T-204**: الشرائح لا تُنشر، وملفٌّ بقي منها لا يُخدم.
     *
     * @return array{0: ?Locale, 1: OutputType}|null
     */
    private function parseRest(string $rest): ?array
    {
        $segments = array_values(array_filter(
            explode('/', $rest),
            static fn (string $s): bool => $s !== '',
        ));

        return match (count($segments)) {
            0 => [null, OutputType::Page],
            1 => $this->withLocale($segments[0]),
            default => null,
        };
    }

    /** @return array{0: Locale, 1: OutputType}|null */
    private function withLocale(string $value): ?array
    {
        $locale = Locale::tryFrom($value);

        return $locale === null ? null : [$locale, OutputType::Page];
    }

    private function tombstone(string $tenantSlug, string $summarySlug): Response
    {
        $contents = $this->store->get(Paths::tombstone($tenantSlug, $summarySlug));

        if ($contents === null) {
            abort(404);
        }

        return response($contents, 410)->header('Content-Type', 'text/html; charset=UTF-8');
    }
}
