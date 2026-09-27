<?php

declare(strict_types=1);

namespace App\Http\Controllers\Settings;

use App\Actions\Team\InviteMember;
use App\Enums\Role;
use App\Http\Controllers\Controller;
use App\Models\TeamInvitation;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use RuntimeException;

/**
 * الفريق — SCREENS.md §10، والمهمّة T-33.
 *
 * ★ **وأثرُ غيابها أنّ الجهة حسابٌ واحد.** فمن أراد محرّراً ثانياً راسلَنا،
 * **ومن ترك موظّفٌ عندها لم تستطع نزع وصوله** — وهذا الثاني أخطر: حسابٌ
 * قائمٌ لمن فارق الجهة، ينشر باسمها ولا يُقفل إلّا منّا.
 *
 * و`UserPolicy` قائمةٌ منذ T-02 بقواعدها كاملة، **ولا شاشة تناديها**.
 */
class TeamController extends Controller
{
    public function index(Request $request): Response
    {
        $tenant = $this->tenant($request);
        $actor = $this->actor($request);

        return Inertia::render('Settings/Team', [
            'members' => User::query()
                ->orderByRaw("case role when 'owner' then 0 when 'editor' then 1 else 2 end")
                ->orderBy('name')
                ->get()
                ->map(fn (User $member): array => [
                    'id' => $member->id,
                    'name' => $member->name,
                    'email' => $member->email,
                    'role' => $member->role->value,
                    'is_self' => $member->id === $actor->id,
                    /*
                     * **ولا يُترك المالك الأخير بلا مالك** — فنزعُه أو
                     * تنزيلُ دوره **يُقفل الجهة على نفسها**: لا فريق،
                     * ولا هوية، ولا اشتراك — ولا مدخل بعده إلّا منّا.
                     */
                    'is_last_owner' => $member->role->isOwner() && $this->owners() === 1,
                ])
                ->values()
                ->all(),

            'invitations' => TeamInvitation::query()
                ->whereNull('accepted_at')
                ->orderByDesc('created_at')
                ->get()
                ->map(static fn (TeamInvitation $invitation): array => [
                    'id' => $invitation->id,
                    'email' => $invitation->email,
                    'role' => $invitation->role->value,
                    'expired' => $invitation->isExpired(),
                    'expires_at' => $invitation->expires_at->toIso8601String(),
                ])
                ->values()
                ->all(),

            'roles' => array_map(static fn (Role $role): string => $role->value, Role::cases()),
            'can_manage' => $actor->can('create', User::class),
            'tenant' => ['name' => $tenant->name_ar],
        ]);
    }

    /** يدعو، **ويعيد الرابط في الجلسة ليُنسخ** — لا يكتفي ببريد. */
    public function invite(Request $request, InviteMember $invite): RedirectResponse
    {
        $this->authorize('create', User::class);

        $data = $request->validate([
            'email' => ['required', 'string', 'email', 'max:255'],
            'role' => ['required', Rule::enum(Role::class)],
        ]);

        try {
            $result = $invite->handle(
                $this->tenant($request),
                $data['email'],
                Role::from($data['role']),
                $this->actor($request),
            );
        } catch (RuntimeException $refused) {
            return back()->withInput()->withErrors(['email' => $refused->getMessage()]);
        }

        /*
         * ★ **والرمز الخامّ يُعرض مرّةً واحدة**، ثمّ لا سبيل إليه: الجدول
         * يحمل تعميته لا هو. فمن أغلق الشاشة قبل النسخ **أعاد الدعوة**،
         * ولا يُسترجع رابطٌ لا نملكه — وهذا هو الثمن المقصود للتعمية.
         */
        return back()->with('invitation_url', route('invitations.show', $result['token']));
    }

    public function revoke(Request $request, TeamInvitation $invitation): RedirectResponse
    {
        $this->authorize('create', User::class);

        $invitation->delete();

        return back()->with('message', trans('team.invite_revoked'));
    }

    public function updateRole(Request $request, User $member): RedirectResponse
    {
        $this->authorize('update', $member);

        $data = $request->validate(['role' => ['required', Rule::enum(Role::class)]]);

        $role = Role::from($data['role']);

        if ($this->wouldStrandTenant($member, $role)) {
            return back()->withErrors(['role' => trans('team.errors.last_owner')]);
        }

        $member->forceFill(['role' => $role->value])->save();

        return back()->with('message', trans('team.role_changed'));
    }

    public function remove(Request $request, User $member): RedirectResponse
    {
        // **ولا يحذف المالك نفسه** — `UserPolicy::delete` تمنعه، فتبقى الجهة
        // بمالك ولا يُقفل صاحبُها على نفسه بضغطةٍ واحدة.
        $this->authorize('delete', $member);

        if ($this->wouldStrandTenant($member, null)) {
            return back()->withErrors(['member' => trans('team.errors.last_owner')]);
        }

        $member->delete();

        return back()->with('message', trans('team.member_removed'));
    }

    /**
     * أيترك هذا الفعلُ الجهةَ بلا مالك؟
     *
     * **والحارس واحدٌ للنزع ولتنزيل الدور**: كلاهما يُنقص عدد المُلّاك،
     * والفرق بينهما في الشكل لا في الأثر.
     */
    private function wouldStrandTenant(User $member, ?Role $becoming): bool
    {
        if (! $member->role->isOwner()) {
            return false;
        }

        if ($becoming !== null && $becoming->isOwner()) {
            return false;
        }

        return $this->owners() === 1;
    }

    private function owners(): int
    {
        return User::query()->where('role', Role::Owner->value)->count();
    }

    private function tenant(Request $request): Tenant
    {
        $tenant = $request->user()?->tenant;

        abort_if($tenant === null, 403);

        return $tenant;
    }

    private function actor(Request $request): User
    {
        $user = $request->user();

        abort_if($user === null, 403);

        return $user;
    }
}
