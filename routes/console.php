<?php

declare(strict_types=1);

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

/*
 * فحص yt-dlp الأسبوعي — المواصفة §5-أ-6 البند ٢.
 *
 * يوتيوب يكسر yt-dlp كثيراً، والانكسار لا يظهر إلا في نداءٍ حقيقي. وبلا
 * هذا لا يُعرف إلّا من شكوى جهةٍ لم يُنتَج ملخّصها.
 *
 * ولا يعمل في CI — CLAUDE.md §2 القاعدة السابعة: `withoutOverlapping`
 * وبيئةُ الإنتاج وحدها.
 */
Schedule::command('khulasah:check-ytdlp')
    ->weeklyOn(1, '04:00')
    ->withoutOverlapping()
    ->environments(['production', 'staging']);

/*
 * مزامنة المصحف وترجماته — T-161.
 *
 * شروط Quran Foundation لا تُجيز حفظ محتواها أكثر من أسبوع بلا مزامنة.
 * وتجري مرّتين في الأسبوع لا مرّة: فإن أخفقت إحداهما بقيت الأخرى قبل المهلة.
 */
Schedule::command('khulasah:sync-quran')
    ->days([1, 4])
    ->at('03:30')
    ->withoutOverlapping()
    ->environments(['production', 'staging']);

/*
 * سقف الإنفاق كلّ ساعة — المواصفة §11.
 *
 * «خطأ في حلقة برمجية قادر على إحراق ألف دولار في ليلة، وهذا السقف هو ما
 * يمنعه». ويعمل في كل البيئات المنشورة، ولا يُستثنى منه شيء.
 */
Schedule::command('khulasah:enforce-spend-cap')
    ->hourly()
    ->withoutOverlapping();

/*
 * تنبيهات الكلفة والأداء — T-22.
 *
 * عند ٨٠٪ من السقف لا ١٠٠٪ — والتفصيل في {@see \App\Console\Commands\CheckCostAlerts}.
 */
Schedule::command('khulasah:check-cost-alerts')
    ->hourly()
    ->withoutOverlapping();

/*
 * كنسُ الملفّات المرفوعة المتروكة — المواصفة §5-أ-4-ب.
 *
 * **كلّ ساعة**: أجزاءُ رفعٍ انقطع ولم يُستأنف تُكنس بعد `UPLOAD_STALE_HOURS`،
 * وملفُّ مهمّةٍ أخفقت إخفاقاً عارضاً بعد `UPLOAD_RETENTION_DAYS`. والملفّ
 * يُحذف قبل ذلك كلّه متى صار نصّاً، أو أُلغيت مهمّته، أو أخفقت لعيبٍ فيه.
 */
Schedule::command('khulasah:prune-uploads')
    ->hourly()
    ->withoutOverlapping();

/*
 * نصوصُ أداة «تحقّق» وتقاريرُها — T-181. **لا تبقى أكثر من سبعة أيام**،
 * فتُكنس كلّ ساعة: ما بلغ مدّته يُحذف في الساعة التي بلغها لا في آخر اليوم.
 */
Schedule::command('khulasah:prune-verify-checks')
    ->hourly()
    ->withoutOverlapping();
