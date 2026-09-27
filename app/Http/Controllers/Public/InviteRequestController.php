<?php

declare(strict_types=1);

namespace App\Http\Controllers\Public;

use App\Enums\InviteRequestKind;
use App\Http\Controllers\Controller;
use App\Models\InviteRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * طلبُ الدعوة من صفحة التعريف — **بلا تسجيل دخول** (T-113).
 *
 * ولا تسجيلَ ذاتيّ في المنتج (SCREENS.md §1)، فهذا البابُ الوحيد لمن
 * لا حساب له. **ولا يُنشئ حساباً ولا جهة**: يقيّد طلباً يُقرأ يدوياً،
 * ثمّ تُرسَل الدعوةُ من لوحة المشرف إن قُبل.
 *
 * والخنقُ على المسار لا في المتحكّم: النموذجُ عامٌّ على جذر الموقع،
 * وهو أوّلُ ما يُغرَق.
 *
 * ★ **ورسائلُ الخطأ بلغة الصفحة** (T-131): `SetLandingLocale` على المسار
 * يقرأ `locale` المرافق للنموذج، فمن أرسل من `/en` يرى خطأه بالإنجليزية.
 * **و`locale` لا يُقيَّد في الصفّ** — هو لغةُ عرضٍ لا حقلٌ من الطلب.
 */
class InviteRequestController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        /*
         * ★ **نوعان بتبويبين** — T-158. والنوعُ الغائبُ أو المجهولُ تواصلٌ،
         * فهو التبويبُ الافتراضيّ وأخفُّ النموذجين: لا يُلزم أحداً بصفةٍ ولا
         * رابطٍ لم يطلبهما.
         */
        $kind = InviteRequestKind::tryFrom((string) $request->input('kind'))
            ?? InviteRequestKind::Contact;
        $lecture = $kind === InviteRequestKind::Lecture;

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            // الصفةُ والرابطُ للتجربة وحدها، ويُسقطان من رسالة التواصل ولو أُرسلا.
            'role' => $lecture ? ['required', 'string', 'max:60'] : ['exclude'],
            'contact' => ['required', 'string', 'max:200'],
            'link' => $lecture ? ['required', 'url', 'max:500'] : ['exclude'],
            /*
             * ★ **حدٌّ سخيّ** — T-142. فحدُّ ٢٠٠٠ حرفٍ يكفي سؤالاً وسياقاً،
             * ولا يفتح باباً لإغراقٍ بجسدٍ ثقيل.
             *
             * **والإلزامُ في التواصل وحده** (T-158): الرسالةُ هناك هي الطلبُ
             * نفسُه، وفي التجربة المحاضرةُ هي الطلب والرسالةُ حاشيةٌ عليه.
             */
            'message' => [$lecture ? 'nullable' : 'required', 'string', 'max:2000'],
        ], [
            'name.required' => __('landing.invite.errors.name'),
            'role.required' => __('landing.invite.errors.role'),
            'contact.required' => __('landing.invite.errors.contact'),
            'link.required' => __('landing.invite.errors.lecture_link'),
            'link.url' => __('landing.invite.errors.link'),
            'message.required' => __('landing.invite.errors.message_required'),
            'message.max' => __('landing.invite.errors.message'),
        ]);

        InviteRequest::create([...$validated, 'kind' => $kind, 'ip' => $request->ip()]);

        return back()
            ->with('invite_sent', __('landing.invite.sent_'.$kind->value))
            ->withFragment('invite');
    }
}
