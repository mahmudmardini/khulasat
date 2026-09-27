<?php

declare(strict_types=1);

namespace App\Support\Analytics;

use App\Enums\Locale;
use App\Http\Controllers\Admin\TenantController;
use App\Models\PageView;
use App\Models\SummaryJob;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

/**
 * توزيعُ القراءات على ألسنة الصفحات — T-140، بلاغُ مالك المنتج.
 *
 * ★ **وواحدةٌ تخدم الشاشتين**: لوحةُ المشرف وشاشةُ الجهة تقرآن التوزيع
 * نفسَه، وحسابُه مرّتين يعني رقمين يفترقان يومَ يتبدّل الحساب — ومالكُ
 * جهةٍ يرى في شاشته غيرَ ما نراه في لوحتنا لا يُصدَّق أحدهما.
 *
 * **والمجموعُ الكلّيُّ لا يُشتقّ من هذه**: {@see SummaryJob}
 * يجمعه بـ`withSum` في استعلامٍ واحد لصفحةٍ كاملة، وجمعُ هذه السطور يُعطي
 * الرقمَ نفسَه — فلا يُحتسب مرّتين ولا ينقص.
 */
final class ViewsByLocale
{
    private function __construct() {}

    /**
     * سطرٌ لكلّ لسانٍ قُرئ، أكثرُها قراءةً أوّلاً.
     *
     * **ولا سطرَ للسانٍ لم يُقرأ**: صفرٌ بجانب لغةٍ نُشرت ولم تُفتح يُقرأ
     * حكماً عليها، وقد نُشرت أمس. ومن أراد اللغاتَ المنشورة وجدها في
     * `outputs` — وهي مسألةٌ أخرى.
     *
     * @return list<array{locale: string|null, locale_label: string, total: int, recent: int}>
     */
    public static function for(int $summaryJobId): array
    {
        return self::rows(
            PageView::query()->where('summary_job_id', $summaryJobId)
        );
    }

    /**
     * التوزيعُ عبر جهةٍ كاملة — لبطاقة الجهة في لوحة المشرف.
     *
     * **و`page_views` بلا حاجزِ مستأجرين** ({@see PageView})، فالتصفيةُ
     * بالعمود صريحةً كما في {@see TenantController}.
     *
     * @return list<array{locale: string|null, locale_label: string, total: int, recent: int}>
     */
    public static function forTenant(int $tenantId): array
    {
        return self::rows(
            PageView::query()->where('tenant_id', $tenantId)
        );
    }

    /**
     * @param  Builder<PageView>  $query
     * @return list<array{locale: string|null, locale_label: string, total: int, recent: int}>
     */
    private static function rows($query): array
    {
        /*
         * **واستعلامٌ واحد لا استعلامٌ لكلّ لغة**، ومجموعُ الكلِّ ومجموعُ
         * ثلاثين يوماً معاً — كما في {@see \App\Http\Controllers\PublicationController}:
         * «رقمٌ تراكميّ وحده يُخفي صفحةً مات عنها القرّاء منذ شهور».
         */
        $rows = $query
            ->selectRaw('locale, sum(views) as total')
            ->selectRaw('sum(case when day >= ? then views else 0 end) as recent', [now()->subDays(30)->toDateString()])
            ->groupBy('locale')
            ->orderByDesc(DB::raw('sum(views)'))
            ->get();

        $out = [];

        foreach ($rows as $row) {
            /*
             * ★ **والفارغُ يُقرأ «غير مبيَّنة» لا «العربية»** — T-140.
             *
             * فصفوفُ ما قبل فصل اللغات مجموعُ اللغات كلِّها في صفٍّ واحد،
             * ونسبتُها إلى اللغة الأولى **تخمينٌ يُعرض رقماً**، والجهةُ
             * تبني عليه قراراً. والاعترافُ بالجهل أصدق.
             */
            $locale = $row->locale instanceof Locale ? $row->locale : null;

            $out[] = [
                'locale' => $locale?->value,
                'locale_label' => $locale?->label() ?? trans('common.views.unattributed'),
                'total' => (int) $row->total,
                'recent' => (int) $row->recent,
            ];
        }

        return $out;
    }
}
