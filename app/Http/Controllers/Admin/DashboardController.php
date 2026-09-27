<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Domain\Summary\JobState;
use App\Enums\ComplaintStatus;
use App\Http\Controllers\Controller;
use App\Models\AuditEvent;
use App\Models\Complaint;
use App\Models\PageView;
use App\Models\SummaryJob;
use App\Models\Tenant;
use Inertia\Inertia;
use Inertia\Response;

/**
 * صدر لوحة المشرف — T-21.
 *
 * **وليست شاشة الكلفة** (SCREENS.md §ج): تلك من T-22 المؤجَّلة، ولا كلفة
 * تُراقَب قبل أوّل تشغيلٍ حقيقي. وما هنا عدٌّ يقول ما حال المنصّة الآن،
 * ومداخلُ إلى الشاشات الثلاث، وآخرُ ما فُعل.
 */
class DashboardController extends Controller
{
    public function __invoke(): Response
    {
        return Inertia::render('Admin/Dashboard', [
            'counts' => [
                'tenants' => Tenant::query()->count(),
                'suspended' => Tenant::query()->where('status', 'suspended')->count(),
                'jobs' => SummaryJob::query()->count(),
                // ما يقف عند الناس، وما وقف عندنا — وهما ما يُنظر فيه أوّلاً.
                'needs_review' => SummaryJob::query()
                    ->where('state', JobState::NeedsReview->value)->count(),
                'failed' => SummaryJob::query()
                    ->where('state', JobState::Failed->value)->count(),
                /*
                 * الاعتراضات — T-28. **والمتأخّرة تُعدّ وحدها**: عددٌ جامع
                 * يقول «سبعٌ مفتوحة» ولا يقول أنّ فيها اثنتين مضت مهلتُهما،
                 * وهاتان هما اللتان يُسأل عنهما.
                 */
                'complaints' => Complaint::query()
                    ->where('status', ComplaintStatus::Open->value)->count(),
                'overdue' => TakedownController::overdueQuery()->count(),
                'running' => SummaryJob::query()
                    ->whereNotIn('state', [
                        JobState::Published->value,
                        JobState::Failed->value,
                        JobState::Cancelled->value,
                        JobState::NeedsReview->value,
                    ])->count(),
            ],

            /*
             * المشاهدات — T-136، وتقرأ `page_views` من T-31.
             *
             * ★ **والمجموعُ وآخرُ ثلاثين معاً لا التراكميُّ وحده.** فنصُّ
             * T-31: «رقمٌ تراكميّ وحده يُخفي صفحةً مات عنها القرّاء منذ
             * شهور». والأوّل يقول كم بلغت، والثاني يقول أحيّةٌ هي اليوم.
             */
            'views' => [
                'total' => (int) PageView::query()->sum('views'),
                'recent' => (int) PageView::query()
                    ->where('day', '>=', now()->subDays(30)->toDateString())
                    ->sum('views'),
            ],
            'audit' => AuditEvent::query()
                ->orderByDesc('id')
                ->limit(15)
                ->get()
                ->map(static fn (AuditEvent $event): array => [
                    'id' => $event->id,
                    'action' => $event->action->value,
                    'sensitive' => $event->action->isSensitive(),
                    'admin_name' => $event->admin_name,
                    'subject_label' => $event->subject_label,
                    'note' => $event->note,
                    'occurred_at' => $event->occurred_at?->toDateTimeString(),
                ])
                ->all(),
        ]);
    }
}
