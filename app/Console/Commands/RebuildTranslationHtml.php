<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Actions\Stages\TranslateSummary;
use App\Enums\Locale;
use App\Models\SummaryTranslation;
use App\Support\I18n\BodyStrings;
use App\Support\Quran\AyahTranslations;
use App\Support\Render\ContentObject;
use Illuminate\Console\Command;
use Throwable;

/**
 * إعادةُ بناء متنِ الترجمات المحفوظة — T-141.
 *
 * ★★ **ولا نموذجَ يُنادى هنا ولا فلسٌ يُصرف.**
 *
 * فـ{@see TranslateSummary::rebuild} تبني `body_html` من `body_json`
 * و`meanings` المحفوظين في الصفّ نفسه، وتمرّ بهما على
 * {@see AyahTranslations} و{@see BodyStrings}
 * — كلُّها حسابٌ محضٌ على بياناتٍ عندنا.
 *
 * **وعلّتُها ضررٌ واقع:** بيئةٌ لم يُبذَر فيها `quran_translations` كتبت
 * `body_html` بلا ترجماتِ آيات عند أوّل إعادة نشر، **فمحت ما كان**. ولا
 * يُصلحها `khulasah:refresh-pages`: العطبُ في الصفّ المحفوظ، والرسمُ يقرؤه
 * كما هو ({@see ContentObject}).
 *
 * **والقرصُ لا يتبدّل بهذه**: هي تُصلح القاعدة، ثمّ `khulasah:refresh-pages`
 * يرفع المصحَّح — وكلتاهما مجّانيّة. وفصلُهما مقصود: من أراد أن يرى ما
 * سيُرفَع قبل أن يُرفَع استطاع.
 */
final class RebuildTranslationHtml extends Command
{
    protected $signature = 'khulasah:rebuild-translation-html
        {--job=* : أرقامُ مهامّ بعينها، وإلّا فالترجماتُ كلُّها}
        {--locale= : لغةٌ بعينها (en|tr|ru)}
        {--dry-run : يُحصي ولا يكتب}';

    protected $description = 'إعادةُ بناء متنِ الترجمات المحفوظة بلا نداء نموذج — تُعيد ترجماتِ الآيات';

    public function handle(TranslateSummary $translate): int
    {
        $locale = $this->option('locale');

        if ($locale !== null && Locale::tryFrom((string) $locale) === null) {
            $this->error('لغةٌ غير معروفة: '.$locale);

            return self::FAILURE;
        }

        /*
         * **ومصدرُها `summary_translations` لا `summary_jobs`**: المقصودُ
         * كلُّ ترجمةٍ محفوظة، ومنها ما لمهمّةٍ أُزيل نشرُها — فتُصلح الآن
         * وتخرج سليمةً يومَ تُنشر.
         */
        $rows = SummaryTranslation::acrossTenants()
            // و`summaryJob` بحاجزه أيضاً: الأمرُ يجري بلا سياقِ جهة.
            ->with(['summaryJob' => fn ($query) => $query->withoutGlobalScopes()])
            ->whereNotNull('body_json')
            ->when($this->option('job') !== [], fn ($query) => $query->whereIn('summary_job_id', $this->option('job')))
            ->when($locale !== null, fn ($query) => $query->where('locale', $locale))
            ->orderBy('summary_job_id')
            ->orderBy('locale')
            ->get();

        if ($rows->isEmpty()) {
            $this->warn('لا ترجمةَ محفوظةً تُعاد.');

            return self::SUCCESS;
        }

        $dry = (bool) $this->option('dry-run');
        $done = 0;
        $failed = 0;
        $gained = 0;

        foreach ($rows as $row) {
            $job = $row->summaryJob;

            if ($job === null) {
                continue;
            }

            // **وعددُ الآيات المترجَمة قبل وبعد** — فالأمرُ يقول ما أصلحه لا
            // أنّه عمل. ومن نفّذه على بيئةٍ سليمةٍ يرى صفراً، وهو الصواب.
            $before = substr_count((string) $row->body_html, 'ayah-tr');

            if ($dry) {
                $this->line("المهمّة {$row->summary_job_id} · {$row->locale->value}: {$before} آية مترجَمة الآن");
                $done++;

                continue;
            }

            try {
                $translate->rebuild($job, $row->locale, $row);

                $after = substr_count((string) $row->fresh()?->body_html, 'ayah-tr');
                $gained += max(0, $after - $before);

                $mark = $after > $before ? " (+{$after} آية)" : '';
                $this->line("المهمّة {$row->summary_job_id} · {$row->locale->value}{$mark}");
                $done++;
            } catch (Throwable $failure) {
                // **وسقوطُ واحدةٍ لا يُوقف البقيّة**: كلُّ ترجمةٍ قائمةٌ بنفسها.
                $this->error("المهمّة {$row->summary_job_id} · {$row->locale->value}: {$failure->getMessage()}");
                $failed++;
            }
        }

        $this->newLine();

        if ($dry) {
            $this->line("ستُعاد {$done} ترجمة.");

            return self::SUCCESS;
        }

        $this->line("أُعيدت {$done} ترجمة، وأخفقت {$failed}، وعادت {$gained} آيةٍ بترجمتها.");

        if ($gained === 0) {
            $this->comment('ولا آيةَ عادت. إن كنتَ تنتظر عودتَها فابذرِ الجدول أوّلاً: khulasah:seed-quran-translations');
        }

        // **والقرصُ لم يتبدّل بعد** — فمن نسيها ظنّ الإصلاحَ تمّاً وهو في القاعدة.
        $this->newLine();
        $this->info('ثمّ ارفعْ المصحَّح إلى القرص: php artisan khulasah:refresh-pages');

        return $failed > 0 ? self::FAILURE : self::SUCCESS;
    }
}
