<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Actions\Admin\RecordAudit;
use App\Enums\AuditAction;
use App\Enums\Role;
use App\Enums\Stage;
use App\Enums\UnverifiedPolicy;
use App\Http\Controllers\Controller;
use App\Models\AuditEvent;
use App\Models\PageView;
use App\Models\SummaryJob;
use App\Models\Tenant;
use App\Models\UsageRecord;
use App\Models\User;
use App\Services\Quota\QuotaGuard;
use App\Support\Analytics\ViewsByLocale;
use App\Support\Billing\Plan;
use App\Support\Model\StagePrompt;
use App\Support\Render\TenantCarouselDesigns;
use App\Support\TenantContext;
use App\Support\Verification\DomainPolicy;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * الجهات — SCREENS.md §أ من لوحة المشرف، والمهمّة T-21.
 *
 * **وهذه الشاشة هي كامل آلية التفعيل والترقية.** لا بوّابة دفع إلكتروني في
 * هذه المرحلة — قرار مالك المنتج 7 أيلول 2026 — والجهة تحوّل المبلغ إلى
 * الحساب البنكي. فرفعُ الحدّ هنا **حدثٌ ماليّ**، والصفّ في سجلّ التدقيق
 * هو الرابط الوحيد بين المال المستلَم والحصّة المرفوعة.
 */
class TenantController extends Controller
{
    /**
     * ألفاظُ الجذر — `routes/web.php`، وT-127. `tenants.slug` يقع على جذر
     * النطاق (`/{slug}/…`)، فمن اختار أحد هذه الألفاظ يبتلع مساراً قائماً
     * فعلاً أو يُبتلَع به — والتصادم يكسر التطبيق لا الملخّص وحده.
     *
     * @var list<string>
     */
    private const RESERVED_SLUGS = ['admin', 'panel', 'complaint', 'invite', 'v', 'up', 'storage', 'build', 'verify', 'api'];

    /** الحدود الخمسة في `tenants` — المواصفة §11، وT-13 يقرأ منها. */
    private const LIMITS = [
        'monthly_quota',
        'daily_cap',
        'max_lecture_minutes',
        'transcription_minutes_quota',
        'regenerations_per_summary',
    ];

    public function __construct(
        private readonly RecordAudit $audit,
        private readonly QuotaGuard $quota,
    ) {}

    public function index(Request $request): Response
    {
        $tenants = Tenant::query()
            ->when($request->string('search')->toString(), fn ($query, string $term) => $query
                ->where(fn ($inner) => $inner
                    ->where('name_ar', 'ilike', "%{$term}%")
                    ->orWhere('slug', 'ilike', "%{$term}%")))
            ->when($request->string('status')->toString(), fn ($query, string $status) => $query
                ->where('status', $status))
            ->orderBy('name_ar')
            ->paginate(25)
            ->withQueryString();

        return Inertia::render('Admin/Tenants/Index', [
            'tenants' => [
                'data' => array_map($this->row(...), $tenants->items()),
                'current_page' => $tenants->currentPage(),
                'last_page' => $tenants->lastPage(),
                'total' => $tenants->total(),
            ],
            'filters' => [
                'search' => $request->string('search')->toString() ?: null,
                'status' => $request->string('status')->toString() ?: null,
            ],
        ]);
    }

    public function show(Tenant $tenant): Response
    {
        $decision = $this->quota->monthlyQuota($tenant);

        return Inertia::render('Admin/Tenants/Show', [
            'tenant' => [
                ...$this->row($tenant),
                'name_ar_full' => $tenant->name_ar_full,
                'domain' => $tenant->domain,
                'on_unverified' => $tenant->on_unverified,
                'created_at' => $tenant->created_at?->toDateString(),
                'limits' => array_combine(
                    self::LIMITS,
                    array_map(static fn (string $field): int => (int) $tenant->{$field}, self::LIMITS),
                ),
            ],
            // قوالبُ الكاروسيل وتعليماتُ توليدها — T-173.
            'carousel_designs' => TenantCarouselDesigns::forScreen($tenant),
            'carousel_prompt' => [
                'custom' => $tenant->carousel_design_prompt,
                'default' => StagePrompt::for(Stage::CarouselDesign, DomainPolicy::DEFAULT),
            ],
            'usage' => [
                'used' => $decision->used,
                'limit' => $decision->allowance,
                /*
                 * كلفةُ شهر هذه الجهة — T-103. وشاشةُ الكلفة تجمعها للجهات
                 * كلِّها، ومن يرفع حدّاً إنّما يرفعه من هنا: فليرَ ما تكلّفه
                 * الجهةُ فعلاً وهو يفعل.
                 *
                 * **والحاجز يُرفع صريحاً** كما في `row()` — المشرف بلا سياق جهة.
                 */
                'cost_usd' => round((float) UsageRecord::acrossTenants()
                    ->where('tenant_id', $tenant->id)
                    ->where('occurred_at', '>=', now()->startOfMonth())
                    ->sum('cost_usd'), 4),

                /*
                 * مشاهداتُ صفحات هذه الجهة — T-136.
                 *
                 * **وهي الوجهُ الآخر للكلفة في القرار نفسه**: من يرفع حدّاً
                 * يرى ما تصرفه الجهةُ فوقه، **وهل يُقرأ ما تنشره**. وجهةٌ
                 * تنشر ولا يُفتح لها شيء حالُها غيرُ حالِ جهةٍ تُقرأ.
                 *
                 * و`page_views` بلا حاجزِ مستأجرين ({@see PageView})، فلا
                 * `acrossTenants` هنا — والعزلُ بشرط `tenant_id` نفسِه.
                 */
                'views' => (int) PageView::query()
                    ->where('tenant_id', $tenant->id)
                    ->sum('views'),
                'views_recent' => (int) PageView::query()
                    ->where('tenant_id', $tenant->id)
                    ->where('day', '>=', now()->subDays(30)->toDateString())
                    ->sum('views'),

                /*
                 * ★ **وتوزيعُها على الألسنة — T-140.** فرقمٌ جامعٌ يقول
                 * «تُقرأ»، والتوزيعُ يقول **بأيّ لسان** — ومن أنفق على ثلاث
                 * لغاتٍ يبني على الثاني قرارَ اللغة القادمة، لا على الأوّل.
                 */
                'views_by_locale' => ViewsByLocale::forTenant((int) $tenant->id),
            ],
            /*
             * الشرائح كما هي في الإعداد — T-23. وتُبثّ كاملةً بحدودها
             * ليرى المشرف **ما سيتغيّر قبل أن يضغط**، لا بعد أن يضغط.
             */
            'plans' => array_values(array_map(
                static fn (Plan $plan): array => [
                    'key' => $plan->key,
                    'name' => $plan->nameAr,
                    'limits' => $plan->limits,
                    'rich_outputs' => $plan->allowsRichOutputs(),
                    // أعمدةُ الجهة على هذه الشريحة كما هي؟ فيُعرف الاستثناء.
                    'matches' => $plan->matches($tenant),
                ],
                Plan::all(),
            )),
            'owners' => $tenant->users()->where('role', Role::Owner->value)
                ->get(['id', 'name', 'email'])->all(),
            // سجلّ هذه الجهة وحدها، وهو ما يُسأل عنه عند مراجعة اشتراكها.
            'audit' => $this->auditFor($tenant),
        ]);
    }

    /**
     * إنشاء جهة ومالكها في خطوة — SCREENS.md §أ.
     *
     * **وكلمةُ المرور تُعرض مرّةً على المشرف ولا تُرسل بريداً.** فالبريد غير
     * مضبوط في هذه المرحلة، ووعدُ «دعوةٍ» لا تصل أسوأ من قولِ الحقّ: يأخذها
     * المشرف ويُسلّمها بيده، ويبدّلها صاحبها.
     */
    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name_ar' => ['required', 'string', 'max:255'],
            'slug' => [
                'required', 'string', 'max:255', 'alpha_dash',
                Rule::unique('tenants', 'slug'),
                Rule::notIn(self::RESERVED_SLUGS),
            ],
            'owner_name' => ['required', 'string', 'max:255'],
            'owner_email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')],
            // ★ **يُختار صراحةً ولا يُفترض صامتاً** — T-163. والنموذج يضع
            // `disclose` ابتداءً، فمن أراد غيره رآه قبل أن يختار.
            'on_unverified' => ['required', Rule::enum(UnverifiedPolicy::class)],
        ]);

        $password = Str::password(14, symbols: false);

        $tenant = DB::transaction(function () use ($data, $password): Tenant {
            $tenant = Tenant::query()->create([
                'name_ar' => $data['name_ar'],
                'slug' => $data['slug'],
                'on_unverified' => $data['on_unverified'],
            ]);

            /*
             * `withoutGlobalScopes` ليس هنا وإنّما `forceFill`: المستخدم
             * الجديد لا يمرّ بحاجز الجهة لأنّه يُنشأ لها، والمشرف بلا سياق
             * جهةٍ أصلاً — فالإسناد صريح.
             */
            User::query()->create([
                'tenant_id' => $tenant->id,
                'name' => $data['owner_name'],
                'email' => $data['owner_email'],
                'password' => Hash::make($password),
                'role' => Role::Owner,
                'email_verified_at' => now(),
            ]);

            return $tenant;
        });

        $this->audit->handle(
            AuditAction::TenantCreated,
            $tenant,
            [
                'slug' => ['from' => null, 'to' => $tenant->slug],
                'on_unverified' => ['from' => null, 'to' => $tenant->on_unverified],
            ],
            $data['owner_email'],
        );

        // مرّةً واحدة في الجلسة، ولا تُقيَّد في السجلّ — سجلٌّ يحمل كلمات
        // المرور يصير هو الخطر الذي يحرس منه.
        return to_route('admin.tenants.show', $tenant)
            ->with('temporary_password', $password);
    }

    /**
     * الحدود الخمسة — وهي التفعيل نفسه.
     *
     * **ولا يُبدَّل حدٌّ بلا سبب مكتوب.** والسبب يربط الرقم بالتحويل البنكي
     * الذي جاء به، وبلا هذا يقول السجلّ «رُفعت الحصّة» ولا يقول لماذا،
     * فلا يُراجَع بعد أشهر.
     */
    public function updateLimits(Request $request, Tenant $tenant): RedirectResponse
    {
        $data = $request->validate([
            'monthly_quota' => ['required', 'integer', 'min:0', 'max:100000'],
            'daily_cap' => ['required', 'integer', 'min:0', 'max:100000'],
            'max_lecture_minutes' => ['required', 'integer', 'min:1', 'max:1440'],
            'transcription_minutes_quota' => ['required', 'integer', 'min:0', 'max:1000000'],
            'regenerations_per_summary' => ['required', 'integer', 'min:0', 'max:100'],
            'note' => ['nullable', 'string', 'max:1000'],
        ]);

        $tenant->fill(array_intersect_key($data, array_flip(self::LIMITS)));

        $changes = $this->audit->diff($tenant, self::LIMITS);

        // حفظٌ بلا تبديل ليس تعديلاً: لا يُطلب له سبب ولا يُقيَّد.
        if ($changes === []) {
            return back();
        }

        if (blank($data['note'] ?? null)) {
            return back()->withErrors(['note' => trans('admin.errors.note_required')]);
        }

        $tenant->save();

        $this->audit->handle(AuditAction::TenantLimits, $tenant, $changes, $data['note']);

        return back()->with('message', trans('admin.tenants.limits_saved'));
    }

    /**
     * تطبيق شريحة — T-23.
     *
     * **والشريحة نقطةُ بداية لا قفل**: تملأ الأعمدة الخمسة مرّةً، ثمّ يبقى
     * كلُّ رقمٍ قابلاً للتعديل وحده من نموذج الحدود أعلاه. فجهةٌ على «مسجد»
     * بحصّةٍ مرفوعة استثناءً حالةٌ واقعية، **ومن أقفل الأرقام على الشريحة
     * اضطرّ إلى اختراع شريحةٍ لكلّ استثناء**.
     *
     * ويُطلب لها سببٌ مكتوب كما يُطلب للحدود: هي تعديلُ حدودٍ بضغطةٍ واحدة،
     * والتحويلُ البنكيّ الذي جاءت به هو ما يُقرأ في السجلّ بعد أشهر.
     */
    public function applyPlan(Request $request, Tenant $tenant): RedirectResponse
    {
        $data = $request->validate([
            'plan' => ['required', 'string', Rule::in(array_keys(Plan::all()))],
            'note' => ['nullable', 'string', 'max:1000'],
        ]);

        Plan::all()[$data['plan']]->applyTo($tenant);

        $changes = $this->audit->diff($tenant, [...self::LIMITS, 'plan']);

        // تطبيقُ شريحةٍ قائمةٍ بحدودها كما هي ليس تعديلاً.
        if ($changes === []) {
            return back();
        }

        if (blank($data['note'] ?? null)) {
            return back()->withErrors(['note' => trans('admin.errors.note_required')]);
        }

        $tenant->save();

        $this->audit->handle(AuditAction::TenantPlan, $tenant, $changes, $data['note']);

        return back()->with('message', trans('admin.tenants.plan_saved'));
    }

    public function updateStatus(Request $request, Tenant $tenant): RedirectResponse
    {
        $data = $request->validate([
            'status' => ['required', Rule::in(['active', 'suspended'])],
            'note' => ['nullable', 'string', 'max:1000'],
        ]);

        $tenant->status = $data['status'];

        $changes = $this->audit->diff($tenant, ['status']);

        if ($changes === []) {
            return back();
        }

        if (blank($data['note'] ?? null)) {
            return back()->withErrors(['note' => trans('admin.errors.note_required')]);
        }

        $tenant->save();

        $this->audit->handle(AuditAction::TenantStatus, $tenant, $changes, $data['note']);

        return back()->with('message', trans('admin.tenants.status_saved'));
    }

    /**
     * وضعُ البيان — T-163، والمواصفة §7-5.
     *
     * **ولا يُبدَّل بلا سبب مكتوب**، كتعليق الجهة: هو ما يقرّر أيمرّ الحديث
     * الضعيف مبيَّناً بلا إنسان أم يقف. ولا يمسّ ما نُشر قبله.
     */
    public function updateVerification(Request $request, Tenant $tenant): RedirectResponse
    {
        $data = $request->validate([
            'on_unverified' => ['required', Rule::enum(UnverifiedPolicy::class)],
            'note' => ['nullable', 'string', 'max:1000'],
        ]);

        $tenant->on_unverified = UnverifiedPolicy::from($data['on_unverified']);

        $changes = $this->audit->diff($tenant, ['on_unverified']);

        if ($changes === []) {
            return back();
        }

        if (blank($data['note'] ?? null)) {
            return back()->withErrors(['note' => trans('admin.verification.note_required')]);
        }

        $tenant->save();

        $this->audit->handle(AuditAction::TenantVerification, $tenant, $changes, $data['note']);

        return back()->with('message', trans('admin.verification.saved'));
    }

    /**
     * صفٌّ في القائمة.
     *
     * **والعدّ يرفع الحاجز صراحةً.** فالمشرف بلا سياق جهة، و`SummaryJob`
     * يحمل `BelongsToTenant` — والرفعُ الصريح يظهر في المراجعة، بخلاف
     * اعتمادٍ صامت على أنّ السياق فارغ.
     *
     * @return array<string, mixed>
     */
    private function row(Tenant $tenant): array
    {
        $jobs = app(TenantContext::class)->withoutScope(
            fn (): int => SummaryJob::query()->where('tenant_id', $tenant->id)->count(),
        );

        return [
            'id' => $tenant->id,
            'name_ar' => $tenant->name_ar,
            'slug' => $tenant->slug,
            'plan' => $tenant->plan,
            'plan_label' => Plan::label($tenant->plan),
            'status' => $tenant->status,
            'monthly_quota' => (int) $tenant->monthly_quota,
            'jobs_count' => $jobs,
        ];
    }

    /** @return list<array<string, mixed>> */
    private function auditFor(Tenant $tenant): array
    {
        return AuditEvent::query()
            ->where('subject_type', 'Tenant')
            ->where('subject_id', $tenant->id)
            ->orderByDesc('occurred_at')
            ->orderByDesc('id')
            ->limit(50)
            ->get()
            ->map(static fn (AuditEvent $event): array => [
                'id' => $event->id,
                'action' => $event->action->value,
                'admin_name' => $event->admin_name,
                'changes' => $event->changes,
                'note' => $event->note,
                'occurred_at' => $event->occurred_at?->toDateTimeString(),
            ])
            ->all();
    }
}
