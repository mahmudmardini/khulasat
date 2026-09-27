<?php

declare(strict_types=1);

namespace App\Http\Controllers\Public;

use App\Actions\Compliance\RecordComplaint;
use App\Enums\ComplaintKind;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

/**
 * نموذج الاعتراض العامّ — **بلا تسجيل دخول** (T-24 البند الأوّل).
 *
 * ورابطُه في تذييل كلّ صفحة منشورة. **ولا يُشترط حسابٌ**: من يعترض غالباً
 * ليس زبوناً — شيخٌ نُسب إليه كلام، أو قارئٌ رأى تخريجاً خطأً. واشتراطُ
 * التسجيل يُغلق الباب على من فُتح لأجله.
 */
class ComplaintController extends Controller
{
    public function create(Request $request): InertiaResponse
    {
        return Inertia::render('Public/Complaint', [
            'url' => $request->string('url')->toString(),
            'kinds' => array_map(
                static fn (ComplaintKind $kind): array => [
                    'value' => $kind->value,
                    'label' => $kind->label(),
                    'sla_hours' => $kind->slaHours(),
                ],
                ComplaintKind::cases(),
            ),
        ]);
    }

    public function store(Request $request, RecordComplaint $record): RedirectResponse
    {
        $validated = $request->validate([
            'kind' => ['required', Rule::in(array_column(ComplaintKind::cases(), 'value'))],
            'url' => ['required', 'url', 'max:500'],
            'contact' => ['required', 'string', 'max:200'],
            'detail' => ['nullable', 'string', 'max:2000'],
        ], [
            'url.required' => 'رابط الصفحة المعترَض عليها مطلوب.',
            'url.url' => 'الرابط غير صالح. انسخه كاملاً من شريط المتصفّح.',
            'contact.required' => 'نحتاج وسيلة تواصل لنُعلمك بما صار إليه اعتراضك.',
        ]);

        $complaint = $record->handle($validated);

        return back()->with('message', sprintf(
            'وصلَنا اعتراضك، وسنردّ عليك خلال %s ساعة.',
            $complaint->kind->slaHours(),
        ));
    }
}
