<?php

declare(strict_types=1);

namespace App\Actions\Admin;

use App\Enums\AuditAction;
use App\Models\AuditEvent;
use App\Models\Complaint;
use App\Models\InviteRequest;
use App\Models\ModelConfig;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Request;

/**
 * يقيّد فعل المشرف — المواصفة §10، والمهمّة T-21.
 *
 * **والفاعل يُقرأ من الحارس لا يُمرَّر.** ولو مُرِّر لجاز أن يُمرَّر خطأً،
 * وسجلُّ تدقيقٍ يقبل اسمَ فاعلٍ من مناديه ليس سجلَّ تدقيق.
 */
class RecordAudit
{
    /**
     * @param  array<string, array{from: mixed, to: mixed}>  $changes
     */
    public function handle(
        AuditAction $action,
        ?Model $subject = null,
        array $changes = [],
        ?string $note = null,
    ): AuditEvent {
        $admin = Auth::guard('admin')->user();

        return AuditEvent::query()->create([
            'admin_id' => $admin?->getAuthIdentifier(),
            // لقطةٌ لا مرجع: الحساب يُحذف ويُعاد تسميته، والسجلّ يبقى مقروءاً.
            'admin_name' => $admin?->name ?? '—',
            'admin_email' => $admin?->email ?? '—',
            'action' => $action->value,
            'subject_type' => $subject === null ? null : class_basename($subject),
            'subject_id' => $subject?->getKey(),
            'subject_label' => $this->label($subject),
            'changes' => $changes,
            'note' => $note,
            'ip' => Request::ip(),
            'occurred_at' => now(),
        ]);
    }

    /**
     * الفرق بين ما كان وما صار، للحقول المطلوبة وحدها.
     *
     * **ويُنادى قبل الحفظ.** فبعده يصير `getOriginal` هو الجديد، ويُقيَّد
     * تغييرٌ من قيمةٍ إلى نفسها.
     *
     * ولا يُقيَّد حقلٌ لم يتبدّل: حفظُ نموذجٍ بلا تعديل ليس تعديلاً، وصفٌّ
     * في السجلّ يقول «من ٣ إلى ٣» ضجيجٌ يُخفي ما تحته.
     *
     * @param  list<string>  $fields
     * @return array<string, array{from: mixed, to: mixed}>
     */
    public function diff(Model $subject, array $fields): array
    {
        $changes = [];

        foreach ($fields as $field) {
            /*
             * `originalIsEquivalent` لا مقارنةٌ بيدنا.
             *
             * **والحقل يصل من النموذج نصّاً** («40») ويُقارن بعددٍ من قاعدة
             * البيانات (40). فـ`===` تراهما مختلفين فتُقيّد تغييراً لم يقع
             * وتطلب له سبباً، و`==` تراهما سواءً وترى `"0"` و`""` سواءً
             * كذلك — وكلاهما خطأ.
             *
             * وهذه دالّة لارافل نفسها: تعرف صبّ العمود فتقارن على أساسه.
             * **ولا تُبدَّل بمقارنةٍ يدوية**: `pint` يقلب `==` إلى `===`
             * بقاعدة `strict_comparison`، فمقارنةٌ فضفاضة هنا لا تثبت.
             */
            if ($subject->originalIsEquivalent($field)) {
                continue;
            }

            $changes[$field] = [
                'from' => $subject->getOriginal($field),
                'to' => $subject->getAttribute($field),
            ];
        }

        return $changes;
    }

    private function label(?Model $subject): ?string
    {
        return match (true) {
            $subject instanceof Tenant => $subject->name_ar,
            $subject instanceof ModelConfig => $subject->stage->value,
            // الرابط المعترَض عليه — وهو ما يُبحث عنه في السجلّ بعد أشهر.
            $subject instanceof Complaint => $subject->url,
            // اسمُ الطالب لقطةً — T-135. والصفُّ يُحذف أو يُعدَّل، والسجلّ يبقى مقروءاً.
            $subject instanceof InviteRequest => $subject->name,
            default => null,
        };
    }
}
