<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Actions\Admin\RecordAudit;
use App\Enums\AuditAction;
use App\Http\Controllers\Controller;
use App\Services\Quota\SpendCap;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * وقفُ سقف الإنفاق أو رفعُه من لوحة المشرف — T-151.
 *
 * **وسبقه** كان لا طريق إليه إلّا `tinker` أو أمر Artisan من الطرفية، فوقفٌ
 * وقع لأجل اختبارٍ يبقى قائماً أيّاماً بلا أن يراه أحد في اللوحة. وسببُه هنا
 * إلزاميٌّ كبقية الأفعال المالية — {@see TenantController::updateStatus}.
 */
class SpendCapController extends Controller
{
    public function __construct(private readonly RecordAudit $audit) {}

    public function update(Request $request, SpendCap $cap): RedirectResponse
    {
        $data = $request->validate([
            'halted' => ['required', 'boolean'],
            'note' => ['required', 'string', 'max:1000'],
        ]);

        $wasHalted = $cap->isHalted();

        // حفظٌ بلا تبديل ليس تعديلاً — كبقية أفعال هذه اللوحة.
        if ($data['halted'] === $wasHalted) {
            return back();
        }

        if ($data['halted']) {
            $cap->halt($data['note']);
            $this->audit->handle(
                AuditAction::SpendCapHalted,
                changes: ['halted' => ['from' => false, 'to' => true]],
                note: $data['note'],
            );
        } else {
            $cap->release();
            $this->audit->handle(
                AuditAction::SpendCapReleased,
                changes: ['halted' => ['from' => true, 'to' => false]],
                note: $data['note'],
            );
        }

        return back()->with('message', trans('admin.cost.spend_cap_saved'));
    }
}
