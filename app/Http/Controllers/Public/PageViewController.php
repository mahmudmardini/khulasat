<?php

declare(strict_types=1);

namespace App\Http\Controllers\Public;

use App\Actions\Analytics\RecordPageView;
use App\Enums\Locale;
use App\Enums\OutputType;
use App\Http\Controllers\Controller;
use App\Models\SummaryJob;
use Illuminate\Http\Response;

/**
 * شاهدة العدّ — SCREENS.md §7، والمهمّة T-31.
 *
 * ★ **صورةٌ لا شيفرة، وهذا هو الحدّ الذي بُنيت عليه المهمّة كلُّها.**
 *
 * فقالب الملخّص **لا يُعدَّل منه CSS ولا JavaScript** — CLAUDE.md §2 القاعدة
 * الأولى. ونقطةُ عدٍّ تُكتب بـ`fetch` تخالفها نصّاً. أمّا صورةٌ بحجم بكسل
 * فبنيةٌ محضة، **والبنية وحدها هي التي تُحوَّل** (صدر `layout.blade.php`).
 *
 * **وللصورة فوق ذلك ثلاث فضائل ليست في الشيفرة:**
 * ١. تعمل ومحرّك JavaScript مطفأ.
 * ٢. **تُصفّي أكثر الزواحف من نفسها**: من يقرأ HTML وحده لا يطلب صورها،
 *    فلا يُعدّ. وهذه تصفيةٌ من طبيعة الوسيلة، لا قائمةَ أسماءٍ تبلى.
 * ٣. لا تحمل عن القارئ شيئاً: **لا كوكي، ولا بصمة، ولا عنوان يُحفظ**.
 */
class PageViewController extends Controller
{
    /** بكسل شفّاف واحد، بصيغة GIF — ٤٣ بايتاً وهو أصغر ما يُردّ. */
    private const PIXEL = 'R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7';

    /**
     * @param  string|null  $locale  يغيب في الصيغة القديمة — T-140، فملفٌّ
     *                               منشورٌ قبلها يبقى يعمل ويقع عدُّه في
     *                               «غير مبيَّنة» لا في لسانٍ مخمَّن.
     *
     * **و`$record` قبل `$locale` لا بعده**: لارافل يملأ ما ليس صنفاً من
     * وُسَعِ المسار بالترتيب، فوسيطٌ اختياريٌّ قبل المحقون يتلقّى المحقونَ
     * نفسَه في المسار القديم — ولا يظهر ذلك إلّا عند الطلب.
     */
    public function __invoke(string $job, string $type, RecordPageView $record, ?string $locale = null): Response
    {
        $output = OutputType::tryFrom($type);
        $summary = $output === null ? null : SummaryJob::acrossTenants()->find($job);

        /*
         * **ولسانٌ لا نعرفه يُقرأ غياباً لا خطأً.** فالمسار عامٌّ ومن جرّب
         * `/v/49/page/zz.gif` لا يُردّ عليه بخطأ — يُعدّ كما تُعدّ الصيغةُ
         * القديمة، **ولا يُكتب في الجدول لسانٌ ليس في {@see Locale}**.
         */
        $language = $locale === null ? null : Locale::tryFrom($locale);

        /*
         * **ويُردّ البكسل في كلّ حال** — ولو لم يوجد الملخّص أو لم يُنشر.
         *
         * فردُّ ٤٠٤ هنا يجعل المسار كاشفاً: من جرّب الأرقام عرف أيّها ملخّصٌ
         * قائم وأيّها لا. **وهو أيضاً أثرٌ يُرى في صفحة القارئ**: صورةٌ
         * مكسورة في صفحةٍ منشورة باسم جهة.
         */
        if ($summary !== null && $output !== null) {
            $record->handle($summary, $output, $language);
        }

        return response((string) base64_decode(self::PIXEL, true))
            ->header('Content-Type', 'image/gif')
            // **بلا كاش**: صورةٌ محفوظة في المتصفّح لا تُطلب، فلا تُعدّ.
            ->header('Cache-Control', 'no-store, no-cache, must-revalidate')
            ->header('Content-Disposition', 'inline');
    }
}
