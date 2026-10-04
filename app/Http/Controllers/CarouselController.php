<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\Render\RenderCarousel;
use App\Exceptions\ModelCallFailed;
use App\Models\SummaryJob;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use RuntimeException;

/**
 * شرائح إنستغرام — SCREENS.md §6 «الكاروسيل»، والمهمّة T-19.
 *
 * **والجهة تنشر على إنستغرام قبل موقعها**، فهذه ليست زينةً على الصفحة بل
 * المخرَج الذي يُشارَك فعلاً.
 *
 * وهنا البناءُ وإعادةُ الصياغة وحدهما. **والعرضُ في تبويب الشرائح بالمعاينة**
 * (T-199): الشرائحُ وصورُها وقالبُها ونصوصُها للنسخ. وكانت لها صفحةٌ منفصلة
 * تعرض كاروسيل الويب بقالبٍ غير قالب الصور، فحُذفت — T-204.
 */
class CarouselController extends Controller
{
    /**
     * يبني الشرائح أو يعيد رسمها.
     *
     * **وإعادة الرسم لا تُحتسب من حصّة إعادة التوليد** — §8-أ. ولذلك لا
     * تمرّ على `RequestRegeneration` كما يمرّ زرّ «أعد المحاولة»: ذاك يُعيد
     * تشغيل الخطّ من أوّله، وهذا يرسم من أصلٍ موجود.
     */
    public function store(Request $request, SummaryJob $job, RenderCarousel $carousel): RedirectResponse
    {
        /*
         * **الشريحة تحرس المخرَج لا الشاشة** — SCREENS.md §3-ب: الكاروسيل
         * «من شريحة مؤسسة فما فوق». والشاشة تبقى مفتوحةً تشرح ما ينقص،
         * «فرؤية ما لا تملكه دافع للترقية، وإخفاؤه يمنع معرفته».
         */
        if (! $job->tenant?->allowsRichOutputs()) {
            return back()->withErrors(['carousel' => trans('jobs.carousel.locked_body')]);
        }

        // النصّ الجديد يُطلب صراحةً — وهو وحده ما يستدعي النموذج ثانيةً.
        $recondense = $request->boolean('recondense');

        try {
            $carousel->handle($job, $recondense);
        } catch (ModelCallFailed $failed) {
            return back()->withErrors(['carousel' => $this->message($failed)]);
        } catch (RuntimeException $refused) {
            return back()->withErrors(['carousel' => $refused->getMessage()]);
        }

        return back();
    }

    /**
     * سبب الإخفاق بالعربية.
     *
     * **ورسالةُ فشل المخطّط تُعرض كما هي**: هي التي تقول «الشريحة الثالثة
     * تجاوزت أربعين كلمة»، وهي معلومةٌ نافعة لمن سيضغط «نصّ جديد». وما عداها
     * رسالةٌ عامّة، فلا يُعرض رمزُ مزوّدٍ على مدير محتوى.
     */
    private function message(ModelCallFailed $failed): string
    {
        return $failed->errorCode === 'schema_validation_failed'
            ? trans('jobs.carousel.rejected', ['reason' => $failed->getMessage()])
            : trans('jobs.carousel.failed');
    }
}
