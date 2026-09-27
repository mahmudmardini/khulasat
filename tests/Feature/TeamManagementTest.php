<?php

declare(strict_types=1);

use App\Actions\Team\InviteMember;
use App\Enums\Role;
use App\Models\TeamInvitation;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Testing\TestResponse;
use Inertia\Testing\AssertableInertia as Assert;

/*
 * الفريق — SCREENS.md §10، والمهمّة T-33.
 *
 * ★ **وأثرُ غيابها أنّ الجهة حسابٌ واحد**: من أراد محرّراً ثانياً راسلَنا،
 * **ومن ترك موظّفٌ عندها لم تستطع نزع وصوله** — حسابٌ قائمٌ لمن فارقها،
 * ينشر باسمها ولا يُقفل إلّا منّا.
 *
 * **والمقياسان الحاكمان:** أنّ `owner` وحده يديره (§10)، **وأنّ الجهة لا
 * تُترك بلا مالك** — فنزعُ آخر مالكٍ يُقفلها على نفسها.
 */

beforeEach(function (): void {
    $this->tenant = Tenant::factory()->create(['name_ar' => 'جهة الاختبار']);
    $this->owner = User::factory()->owner()->for_($this->tenant)->create(['name' => 'المالك']);
    $this->editor = User::factory()->editor()->for_($this->tenant)->create(['name' => 'المحرّر']);
});

function inviteAs(User $actor, string $email = 'new@tenant-a.test', string $role = 'editor'): TestResponse
{
    return test()->actingAs($actor)->post('/panel/settings/team/invitations', [
        'email' => $email,
        'role' => $role,
    ]);
}

// ── الصلاحية ────────────────────────────────────────────────────

it('يعرض الفريق لأعضائه، ولا يُدير إلّا المالك', function (): void {
    $this->actingAs($this->owner)->get('/panel/settings/team')
        ->assertOk()
        ->assertInertia(fn (Assert $page): Assert => $page
            ->component('Settings/Team')
            ->where('can_manage', true)
            ->has('members', 2)
        );

    // **والمحرّر يرى ولا يُدير** — §10: «الفريق `owner` فقط».
    $this->actingAs($this->editor)->get('/panel/settings/team')
        ->assertOk()
        ->assertInertia(fn (Assert $page): Assert => $page->where('can_manage', false));
});

it('يمنع المحرّر من الدعوة ومن تبديل الصلاحيات ومن النزع', function (): void {
    inviteAs($this->editor)->assertForbidden();

    $this->actingAs($this->editor)
        ->put("/panel/settings/team/members/{$this->owner->id}", ['role' => 'viewer'])
        ->assertForbidden();

    $this->actingAs($this->editor)
        ->delete("/panel/settings/team/members/{$this->owner->id}")
        ->assertForbidden();

    expect($this->owner->refresh()->role)->toBe(Role::Owner);
});

it('لا يُري جهةً فريقَ جهةٍ أخرى', function (): void {
    $stranger = User::factory()->owner()->for_(Tenant::factory()->create())->create();

    $this->actingAs($stranger)->get('/panel/settings/team')
        ->assertInertia(fn (Assert $page): Assert => $page->has('members', 1));
});

// ── الدعوة ──────────────────────────────────────────────────────

/**
 * ★ **والمخرَج رابطٌ يُنسخ، لا بريدٌ وحده.**
 *
 * فالبريد لا يصل دائماً — مرشّحُ المهملات، وعنوانٌ كُتب خطأً، ومرسِلٌ لم
 * يُضبط. **وهذه الجهات تتواصل بالمراسلة الفورية أكثر من البريد أصلاً.**
 */
it('يعطي المالك رابطاً يُنسخ عند الدعوة', function (): void {
    inviteAs($this->owner)->assertRedirect()->assertSessionHas('invitation_url');

    $url = (string) session('invitation_url');

    expect($url)->toContain('/panel/invitations/');

    /*
     * **ويبلغ الشاشة فعلاً.** فالوميض في الجلسة لا يصل الواجهة من نفسه:
     * Inertia يبثّ ما تشاركه الوسيطة وحده. **وكان الرابط يُوضع في الجلسة
     * ولا يُعرض** — ظهر عند تشغيل الشاشة، والدعوةُ تُنشأ بلا رابطٍ يُنسخ.
     */
    test()->actingAs($this->owner)->get('/panel/settings/team')
        ->assertInertia(fn (Assert $page): Assert => $page->where('flash.invitation_url', $url));

    $invitation = TeamInvitation::query()->where('email', 'new@tenant-a.test')->first();

    expect($invitation)->not->toBeNull()
        ->and($invitation->role)->toBe(Role::Editor)
        ->and($invitation->isPending())->toBeTrue();
});

/**
 * **والرمز مُعمّى في الجدول.**
 *
 * فمن قرأه — نسخةً احتياطية أو سجلَّ استعلامات — يستطيع بالرابط الخامّ
 * **أن يدخل جهةً بدور مالكها**.
 */
it('لا يحفظ رمز الدعوة خامّاً', function (): void {
    inviteAs($this->owner);

    $url = (string) session('invitation_url');
    $token = basename(parse_url($url, PHP_URL_PATH) ?: '');

    $invitation = TeamInvitation::query()->where('email', 'new@tenant-a.test')->first();

    expect($token)->not->toBe('')
        ->and($invitation->token_hash)->not->toBe($token)
        ->and($invitation->token_hash)->toBe(hash('sha256', $token));
});

it('يستبدل الدعوة المعلَّقة ولا يكدّسها', function (): void {
    inviteAs($this->owner, role: 'viewer');
    inviteAs($this->owner, role: 'editor');

    $pending = TeamInvitation::query()->where('email', 'new@tenant-a.test')->get();

    // الرابط الأخير هو العامل، ولا يبقى لبريدٍ واحد رابطان صالحان.
    expect($pending)->toHaveCount(1)
        ->and($pending->first()->role)->toBe(Role::Editor);
});

/**
 * **ولا يُدعى من له حسابٌ أصلاً.**
 *
 * فالبريد فريدٌ في `users`، فقبولُ الدعوة يخفق برسالة قاعدة بيانات لا
 * يفهمها أحد — **بعد أن أُرسل الرابط وانتظر صاحبه**.
 */
it('يرفض دعوة بريدٍ له حساب، ويقولها عند الدعوة لا عند القبول', function (): void {
    inviteAs($this->owner, $this->editor->email)
        ->assertSessionHasErrors('email');

    expect(TeamInvitation::query()->count())->toBe(0);
});

it('يلغي دعوةً معلَّقة فيبطل رابطها', function (): void {
    inviteAs($this->owner);

    $url = (string) session('invitation_url');
    $invitation = TeamInvitation::query()->first();

    $this->actingAs($this->owner)
        ->delete("/panel/settings/team/invitations/{$invitation->id}")
        ->assertRedirect();

    $this->get($url)
        ->assertOk()
        ->assertInertia(fn (Assert $page): Assert => $page->where('valid', false));
});

// ── القبول ──────────────────────────────────────────────────────

it('ينشئ العضو بالدور المدعوّ إليه ويُدخله', function (): void {
    inviteAs($this->owner, 'viewer@tenant-a.test', 'viewer');

    $token = basename(parse_url((string) session('invitation_url'), PHP_URL_PATH) ?: '');

    $this->post('/panel/invitations', [
        'token' => $token,
        'name' => 'المطّلع',
        'password' => 'a-long-fresh-secret-9',
        'password_confirmation' => 'a-long-fresh-secret-9',
    ])->assertRedirect(route('lectures.index'));

    $member = User::acrossTenants()->where('email', 'viewer@tenant-a.test')->first();

    expect($member)->not->toBeNull()
        ->and($member->role)->toBe(Role::Viewer)
        ->and((int) $member->tenant_id)->toBe((int) $this->tenant->id)
        ->and(Hash::check('a-long-fresh-secret-9', $member->password))->toBeTrue();

    // **ويُدخَل تلقائياً — بخلاف استعادة كلمة المرور (T-32)**: حسابٌ أُنشئ
    // الآن على حارس الجهة قطعاً، فلا يقع الالتباس الذي مُنع هناك.
    expect(auth('web')->id())->toBe($member->id);
});

it('يبطل رابط الدعوة بعد قبوله مرّةً', function (): void {
    inviteAs($this->owner);

    $token = basename(parse_url((string) session('invitation_url'), PHP_URL_PATH) ?: '');

    $payload = [
        'token' => $token,
        'name' => 'الأوّل',
        'password' => 'a-long-fresh-secret-9',
        'password_confirmation' => 'a-long-fresh-secret-9',
    ];

    $this->post('/panel/invitations', $payload)->assertRedirect(route('lectures.index'));

    $this->post('/panel/invitations', [...$payload, 'name' => 'الثاني'])
        ->assertSessionHasErrors('token');

    expect(User::acrossTenants()->where('email', 'new@tenant-a.test')->count())->toBe(1);
});

it('يرفض رابطاً منتهياً ويقول حاله بدل أن يردّ صفحةً مفقودة', function (): void {
    inviteAs($this->owner);

    $token = basename(parse_url((string) session('invitation_url'), PHP_URL_PATH) ?: '');

    TeamInvitation::query()->update(['expires_at' => now()->subDay()]);

    // **والحال تُقال ولا يُردّ ٤٠٤**: رابطٌ منتهٍ يردّ «غير موجود» يُقرأ
    // عطلاً عندنا، فيُراسَل به من دعا ويظنّ المنتج معطوباً.
    $this->get("/panel/invitations/{$token}")
        ->assertOk()
        ->assertInertia(fn (Assert $page): Assert => $page
            ->component('Auth/AcceptInvitation')
            ->where('valid', false)
        );

    $this->post('/panel/invitations', [
        'token' => $token,
        'name' => 'المتأخّر',
        'password' => 'a-long-fresh-secret-9',
        'password_confirmation' => 'a-long-fresh-secret-9',
    ])->assertSessionHasErrors('token');
});

it('لا يكشف بيانات الجهة لرابطٍ غير صحيح', function (): void {
    $this->get('/panel/invitations/not-a-real-token')
        ->assertOk()
        ->assertInertia(fn (Assert $page): Assert => $page
            ->where('valid', false)
            ->where('tenant', null)
            ->where('email', null)
        );
});

// ── الصلاحيات والنزع ────────────────────────────────────────────

it('يبدّل صلاحية عضو', function (): void {
    $this->actingAs($this->owner)
        ->put("/panel/settings/team/members/{$this->editor->id}", ['role' => 'viewer'])
        ->assertRedirect();

    expect($this->editor->refresh()->role)->toBe(Role::Viewer);
});

it('ينزع وصول عضو ويُبقي ما أنشأه للجهة', function (): void {
    $this->actingAs($this->owner)
        ->delete("/panel/settings/team/members/{$this->editor->id}")
        ->assertRedirect();

    expect(User::acrossTenants()->whereKey($this->editor->id)->exists())->toBeFalse();
});

/**
 * ★ **الاختبار الحاكم في هذا الملفّ.**
 *
 * فنزعُ آخر مالكٍ — أو تنزيلُ دوره — **يُقفل الجهة على نفسها**: لا فريق،
 * ولا هوية، ولا اشتراك، ولا مدخل بعده إلّا منّا. والحارس واحدٌ للفعلين،
 * لأنّ الفرق بينهما في الشكل لا في الأثر.
 */
it('لا يترك الجهة بلا مالك، نزعاً كان أو تنزيلَ دور', function (): void {
    $second = User::factory()->owner()->for_($this->tenant)->create();

    // ومع مالكَين يجوز الفعل.
    $this->actingAs($this->owner)
        ->put("/panel/settings/team/members/{$second->id}", ['role' => 'editor'])
        ->assertSessionHasNoErrors();

    // ولمّا بقي واحد، يُمنع الفعلان معاً.
    $this->actingAs($second)
        ->put("/panel/settings/team/members/{$this->owner->id}", ['role' => 'editor'])
        ->assertSessionHasErrors('role');

    $this->actingAs($second)
        ->delete("/panel/settings/team/members/{$this->owner->id}")
        ->assertSessionHasErrors('member');

    expect($this->owner->refresh()->role)->toBe(Role::Owner)
        ->and(User::acrossTenants()->whereKey($this->owner->id)->exists())->toBeTrue();
});

it('لا يدع المالك ينزع نفسه', function (): void {
    // **`UserPolicy::delete` تمنعه** — فلا يُقفل صاحبُها على نفسه بضغطة.
    $this->actingAs($this->owner)
        ->delete("/panel/settings/team/members/{$this->owner->id}")
        ->assertForbidden();
});

it('لا يمسّ عضواً في جهةٍ أخرى', function (): void {
    $foreign = User::factory()->editor()->for_(Tenant::factory()->create())->create();

    // والحاجز يمنع رؤيته أصلاً، فالربط لا يجده.
    $this->actingAs($this->owner)
        ->delete("/panel/settings/team/members/{$foreign->id}")
        ->assertNotFound();

    expect(User::acrossTenants()->whereKey($foreign->id)->exists())->toBeTrue();
});

// ── الفعل مباشرةً ───────────────────────────────────────────────

it('يرفض الفعل دعوةَ بريدٍ مسجَّل في جهةٍ أخرى', function (): void {
    $other = User::factory()->editor()->for_(Tenant::factory()->create())->create();

    expect(fn () => app(InviteMember::class)->handle($this->tenant, $other->email, Role::Editor, $this->owner))
        ->toThrow(RuntimeException::class);
});
