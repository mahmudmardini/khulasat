<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Actions\Admin\RecordAudit;
use App\Actions\Publish\UnpublishSummary;
use App\Enums\AuditAction;
use App\Enums\ComplaintKind;
use App\Enums\ComplaintStatus;
use App\Http\Controllers\Controller;
use App\Models\Complaint;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * الاعتراضات — SCREENS.md §هـ، والمهمّة T-28.
 *
 * ★ **وهذه الشاشة هي النصف الغائب من مسار T-24.** فذاك بنى النموذج العامّ
 * وجدول `complaints` وفعلَ الإزالة، **ولا موضع يُقرأ فيه ما وصل**: الشكوى
 * تُسجَّل ولا يراها أحد، ومهلةُ الثمانِ والأربعين ساعة تمضي بلا علم.
 *
 * **ومن يعترض غالباً ليس زبوناً** — شيخٌ نُسب إليه كلام، أو قارئٌ رأى
 * تخريجاً خطأً. فتجاهلُه صامتاً خطرٌ على السمعة قبل أن يكون نقصاً في ميزة.
 */
class TakedownController extends Controller
{
    public function __construct(
        private readonly RecordAudit $audit,
        private readonly UnpublishSummary $unpublish,
    ) {}

    public function index(Request $request): Response
    {
        $status = $request->string('status')->toString();
        $kind = $request->string('kind')->toString();

        $complaints = Complaint::query()
            ->with(['tenant:id,name_ar,slug', 'summaryJob:id,slug,published_at,unpublished_at'])
            ->when($status !== '', fn (Builder $query) => $query->where('status', $status))
            ->when($kind !== '', fn (Builder $query) => $query->where('kind', $kind))
            /*
             * ★ **الأقربُ إلى مهلته أوّلاً — لا الأحدثُ وصولاً.**
             *
             * وهذا ترتيبُ الشاشة كلِّها لا تفصيلاً فيها: صندوقٌ مرتَّبٌ
             * بالأحدث يدفن شكوى الأمس تحت شكاوى اليوم، **وهي التي بقيت لها
             * ساعتان**. والمهلة تُشتقّ من نوع الاعتراض ({@see ComplaintKind::slaHours()})،
             * فتُحسب في SQL لا في PHP كي يبقى الترتيب صحيحاً عبر الصفحات.
             */
            ->orderByRaw(self::dueSql().' asc')
            ->orderByDesc('id')
            ->paginate(30)
            ->withQueryString();

        return Inertia::render('Admin/Takedowns/Index', [
            'complaints' => [
                'data' => array_map($this->row(...), $complaints->items()),
                'current_page' => $complaints->currentPage(),
                'last_page' => $complaints->lastPage(),
                'total' => $complaints->total(),
            ],
            'filters' => [
                'status' => $status ?: null,
                'kind' => $kind ?: null,
            ],
            'counts' => [
                'open' => Complaint::query()->where('status', ComplaintStatus::Open->value)->count(),
                'overdue' => self::overdueQuery()->count(),
            ],
            'kinds' => array_column(ComplaintKind::cases(), 'value'),
            'statuses' => ComplaintStatus::values(),
        ]);
    }

    /**
     * حسمُ اعتراض — إزالةً، أو معالجةً بغيرها، أو ردّاً.
     *
     * **ولا يُحسم بلا نصٍّ مكتوب.** وهو هنا أوجبُ منه في تعديل الحدود
     * (T-21): ذاك يُقرأ عندنا، **وهذا يُبلَّغ به صاحبُ الجهة** حين تُزال
     * صفحتُه — ومن أزال صفحةً ولم يقل لماذا ترك صاحبها يظنّ العطلَ عندنا.
     */
    public function update(Request $request, Complaint $complaint): RedirectResponse
    {
        $data = $request->validate([
            'status' => [
                'required',
                Rule::in([
                    ComplaintStatus::Unpublished->value,
                    ComplaintStatus::Resolved->value,
                    ComplaintStatus::Dismissed->value,
                ]),
            ],
            'resolution' => ['required', 'string', 'min:3', 'max:2000'],
        ]);

        $status = ComplaintStatus::from($data['status']);

        // **والمحسوم لا يُحسم مرّتين.** وإزالةٌ ثانية تكتب شاهدةً فوق شاهدة
        // وتُقيّد فعلاً لم يقع.
        if ($complaint->status->isSettled()) {
            return back()->withErrors(['status' => trans('admin.errors.complaint_settled')]);
        }

        if ($status === ComplaintStatus::Unpublished) {
            $job = $complaint->summaryJob;

            /*
             * **ولا تُزال صفحةٌ لا نعرفها.** والرابط يُقرأ عند الاستقبال ولا
             * يُوثَق به (T-24)، فقد يصل خطأً أو لصفحةٍ حُذفت — والشكوى تُقيَّد
             * على كلّ حال. فمن أراد إزالةً بلا ملخّصٍ مرتبط فليُحلّها يدوياً.
             */
            if ($job === null) {
                return back()->withErrors(['status' => trans('admin.errors.complaint_no_target')]);
            }

            // `UnpublishSummary` من T-15: يمسح الملفّات ويكتب شاهدة 410
            // مكانها — **410 لا 404** (§9).
            $this->unpublish->handle($job);
        }

        $complaint->forceFill([
            'status' => $status->value,
            'resolution' => $data['resolution'],
            // زمن المعالجة — وهو ما يجعل الوعد مقيساً (T-24).
            'resolved_at' => now(),
        ])->save();

        $this->audit->handle(
            match ($status) {
                ComplaintStatus::Unpublished => AuditAction::ComplaintUnpublished,
                ComplaintStatus::Resolved => AuditAction::ComplaintResolved,
                default => AuditAction::ComplaintDismissed,
            },
            $complaint,
            ['status' => ['from' => ComplaintStatus::Open->value, 'to' => $status->value]],
            $data['resolution'],
        );

        return back()->with('message', trans('admin.takedowns.saved'));
    }

    /**
     * المتأخّرة عن مهلتها — تُستعمل في العدّ وفي صدر اللوحة.
     *
     * @return Builder<Complaint>
     */
    public static function overdueQuery(): Builder
    {
        return Complaint::query()
            ->where('status', ComplaintStatus::Open->value)
            ->whereRaw(self::dueSql().' < now()');
    }

    /**
     * موعدُ الاستحقاق محسوباً في SQL.
     *
     * **والمهلة تختلف بنوع الاعتراض** ({@see ComplaintKind::slaHours()})،
     * فلا يصلح ترتيبٌ على `received_at` وحده: شكوى تخريجٍ وصلت قبل يومين
     * مهلتُها أسبوع، وطلبُ إزالةٍ وصل اليوم مهلتُه ساعات — والثاني أولى.
     *
     * وتُبنى الحالةُ من قيم الـ enum نفسها فلا يفترق الحسابان: من زاد نوعاً
     * ونسي هذا السطر أخرج ترتيباً يخالف ما تعرضه الشاشة.
     */
    private static function dueSql(): string
    {
        $cases = '';

        foreach (ComplaintKind::cases() as $kind) {
            $cases .= " when '{$kind->value}' then interval '{$kind->slaHours()} hours'";
        }

        return "(received_at + case kind{$cases} else interval '48 hours' end)";
    }

    /** @return array<string, mixed> */
    private function row(Complaint $complaint): array
    {
        return [
            'id' => $complaint->id,
            'kind' => $complaint->kind->value,
            'kind_label' => $complaint->kind->label(),
            'status' => $complaint->status->value,
            'url' => $complaint->url,
            'contact' => $complaint->contact,
            'detail' => $complaint->detail,
            'tenant' => $complaint->tenant?->name_ar,
            'summary_job_id' => $complaint->summary_job_id,

            /*
             * حالُ الصفحة — **ثلاثٌ لا اثنتان**.
             *
             * وكانت `is_published` منطقيّةً، فرابطٌ لم يُطابق شيئاً (وهو
             * وارد: الرابط يُقرأ عند الاستقبال ولا يُوثَق به — T-24) يقرأ
             * `false` **فيُعرض «الصفحة مُزالة»** — وهي لم تكن موجودةً أصلاً.
             * فيظنّ المشرف أنّ الإزالة نُفّذت.
             */
            'target' => match (true) {
                $complaint->summaryJob === null => 'unknown',
                $complaint->summaryJob->published_at !== null => 'live',
                default => 'removed',
            },
            'received_at' => $complaint->received_at->toIso8601String(),
            'due_at' => $complaint->dueAt()->toIso8601String(),
            // **تُحسب في الخادم**: ساعةُ من يقرأ قد تكون مضبوطةً على غير الحقيقة.
            'hours_left' => (int) round($complaint->hoursLeft()),
            'overdue' => ! $complaint->status->isSettled() && $complaint->isOverdue(),
            'resolution' => $complaint->resolution,
            'resolved_at' => $complaint->resolved_at?->toIso8601String(),
        ];
    }
}
