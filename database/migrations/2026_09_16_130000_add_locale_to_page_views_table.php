<?php

declare(strict_types=1);

use App\Enums\Locale;
use App\Support\Publish\Beacon;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * لسانُ الصفحة المقروءة — T-140، بلاغُ مالك المنتج.
 *
 * **وكانت الشاهدةُ واحدةً للغات كلِّها:** {@see Beacon}
 * يبنيها من رقم المهمّة والنوع وحدهما، فصفحاتُ العربية والإنجليزية والتركية
 * تطلب `/v/49/page.gif` نفسَها ويكتبها العدّادُ في صفٍّ واحد. فالمجموعُ
 * صحيح **وتوزيعُه معدوم** — ومن أنفق على ثلاث لغاتٍ لا يعرف أنفعَها.
 *
 * ★★ **و`null` تعني «قبل الفصل» لا «العربية».**
 *
 * فصفوفُ ما قبل هذه الهجرة **مجموعُ اللغات كلِّها** في صفٍّ واحد، ونسبتُها
 * إلى اللغة الأولى تخمينٌ يُعرض رقماً — وهو أسوأ من الاعتراف بالجهل، لأنّ
 * الجهةَ تبني عليه قراراً. فتبقى فارغةً وتُقرأ في الواجهة «غير مبيَّنة».
 */
return new class extends Migration
{
    /**
     * ★★ **ومفتاحٌ على `coalesce` لا على العمود — وهذه علّةُ صحّةٍ لا ذوق.**
     *
     * فـPostgres يعدّ `null` **مميَّزاً عن `null`** في المفاتيح الفريدة،
     * فمفتاحٌ على `locale` عارياً لا يمنع صفّين فارغين — و`on conflict`
     * لا يجد الصفَّ الفارغ القائم فيُدرج صفّاً جديداً. **وكلُّ فتحةٍ من
     * ملفٍّ منشورٍ قبل T-140 تصير صفّاً**، وهو بالضبط ما نصّت هجرةُ T-31
     * على منعه: «صفٌّ لكلّ زيارة يعني جدولاً ينمو بلا حدّ».
     *
     * و`coalesce(locale,'')` تجعل الفارغَ قيمةً تُطابَق، فيبقى الفارغُ صفّاً
     * واحداً في اليوم كما كان قبل الهجرة تماماً.
     *
     * **و`''` ليست لغةً في {@see Locale}**، فلا تلتبس بلسانٍ.
     */
    private const INDEX = 'page_views_job_type_locale_day_unique';

    public function up(): void
    {
        Schema::table('page_views', function (Blueprint $table): void {
            $table->string('locale')->nullable()->after('output_type');

            $table->dropUnique(['summary_job_id', 'output_type', 'day']);
        });

        DB::statement(
            'create unique index '.self::INDEX.
            ' on page_views (summary_job_id, output_type, (coalesce(locale, \'\')), day)'
        );
    }

    public function down(): void
    {
        DB::statement('drop index if exists '.self::INDEX);

        /*
         * **والنزولُ يجمع ما فُصل قبل أن يُعيد المفتاح القديم.** فلو أُعيد
         * أوّلاً لاصطدم بثلاثة صفوفٍ لمهمّةٍ ويومٍ واحد، فيخفق النزولُ
         * ويترك الجدول بلا مفتاحٍ يحرسه.
         */
        DB::statement(<<<'SQL'
            with merged as (
                select min(id) as keep, sum(views) as views
                from page_views
                group by summary_job_id, output_type, day
            )
            update page_views set views = merged.views
            from merged
            where page_views.id = merged.keep
            SQL);

        DB::statement(<<<'SQL'
            delete from page_views where id not in (
                select min(id) from page_views group by summary_job_id, output_type, day
            )
            SQL);

        Schema::table('page_views', function (Blueprint $table): void {
            $table->dropColumn('locale');
            $table->unique(['summary_job_id', 'output_type', 'day']);
        });
    }
};
