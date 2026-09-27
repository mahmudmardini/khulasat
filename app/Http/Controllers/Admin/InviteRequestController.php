<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Actions\Admin\RecordAudit;
use App\Enums\AuditAction;
use App\Http\Controllers\Controller;
use App\Models\InviteRequest;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * طلبات الدعوة — T-135، وهي النصفُ الغائب من T-113.
 *
 * ★ **فذاك بنى النموذجَ العامّ وجدولَ `invite_requests`، ولا موضعَ يُقرأ
 * فيه ما وصل.** والطلبُ يُكتب في القاعدة ويُنسى، و`handled_at` عمودٌ
 * مهيَّأ لا يكتبه أحد. **وهذه عينُ ثغرة T-28** في بابٍ آخر: الشكوى
 * تُسجَّل ولا يراها أحد.
 *
 * **وصاحبُ الطلب غريبٌ ينتظر جواباً** — لا زبونٌ له لوحةٌ يشكو فيها، ولا
 * حسابٌ نصله به. فتجاهلُه صامتاً يُفقد عميلاً لم يُعلَم أنّه طلب.
 */
class InviteRequestController extends Controller
{
    public function __construct(private readonly RecordAudit $audit) {}

    public function index(Request $request): Response
    {
        $pending = $request->boolean('pending');

        $requests = InviteRequest::query()
            ->when($pending, fn (Builder $query) => $query->whereNull('handled_at'))
            /*
             * ★ **غيرُ المعالَج أوّلاً، ثمّ الأحدثُ وصولاً.**
             *
             * وهذا ترتيبُ صندوقٍ يُفرَّغ لا قائمةٍ تُقرأ — كترتيب T-28
             * بالمهلة. فقائمةٌ مرتَّبةٌ بالأحدث وحده تدفن طلبَ الأمس
             * المنتظِر تحت طلباتِ اليوم المعالَجة.
             *
             * ولا مهلةَ محسوبةً هنا كما في الاعتراضات: لا وعدَ زمنيّاً في
             * العرض التجاري لطلبِ الدعوة، فالتمييزُ بالانتظار وحده.
             */
            ->orderByRaw('handled_at is null desc')
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate(30)
            ->withQueryString();

        return Inertia::render('Admin/Invites/Index', [
            'requests' => [
                'data' => array_map($this->row(...), $requests->items()),
                'current_page' => $requests->currentPage(),
                'last_page' => $requests->lastPage(),
                'total' => $requests->total(),
            ],
            'filters' => ['pending' => $pending],
            'counts' => [
                'pending' => InviteRequest::query()->whereNull('handled_at')->count(),
            ],
        ]);
    }

    /**
     * تعليمُ الطلب معالَجاً — أو رفعُ التعليم عنه.
     *
     * **والرفعُ مقصودٌ لا زيادة**: التعليمُ ضغطةٌ واحدة في قائمةٍ طويلة،
     * وبلا رجعةٍ عنه يصير الخطأُ نهائياً — فيُدفن طلبٌ لم يُجَب.
     */
    public function update(Request $request, InviteRequest $inviteRequest): RedirectResponse
    {
        $handled = $request->validate([
            'handled' => ['required', 'boolean'],
        ])['handled'];

        $was = $inviteRequest->handled_at;

        // **ولا يُقيَّد فعلٌ لم يقع.** وضغطتان على «معالَج» صفّان في السجلّ
        // يقولان الشيء نفسه مرّتين، ويُخفيان ما تحتهما.
        if (($was !== null) === (bool) $handled) {
            return back();
        }

        $inviteRequest->forceFill([
            'handled_at' => $handled ? now() : null,
        ])->save();

        $this->audit->handle(
            AuditAction::InviteRequestHandled,
            $inviteRequest,
            ['handled_at' => [
                'from' => $was?->toIso8601String(),
                'to' => $inviteRequest->handled_at?->toIso8601String(),
            ]],
        );

        return back()->with('message', trans('admin.invites.saved'));
    }

    /** @return array<string, mixed> */
    private function row(InviteRequest $request): array
    {
        return [
            'id' => $request->id,
            // رسالةُ تواصلٍ أم طلبُ تجربةٍ بمحاضرة — T-158.
            'kind' => $request->kind->value,
            'name' => $request->name,
            'role' => $request->role,
            'contact' => $request->contact,
            'link' => $request->link,

            /*
             * ★ **الرسالةُ تُرسَل كاملةً لا مبتورةً** — T-142. وهي الحقلُ
             * الوحيد الذي كتبه صاحبُ الطلب بنفسه، وفيه ما أراد؛ فقصُّها
             * إلى سطرٍ في الخادم يُخفي السبب الذي من أجله أضيف الحقل.
             * والحدُّ ٢٠٠٠ حرفاً، فلا حِملَ في إرسالها.
             */
            'message' => $request->message,

            /*
             * **و`ip` لا تُعرض.** والهجرةُ تنصّ على غرضها: «للحدّ من الإغراق
             * ولمعرفة مصدر الطلب — **لا لتتبّع الزائر**». وعرضُها في شاشةٍ
             * تُقرأ يومياً يُحوّلها إلى الثاني بلا قرارٍ من أحد.
             */
            'created_at' => $request->created_at?->toIso8601String(),
            'handled_at' => $request->handled_at?->toIso8601String(),
        ];
    }
}
