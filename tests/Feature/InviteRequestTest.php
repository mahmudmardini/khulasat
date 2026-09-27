<?php

declare(strict_types=1);

use App\Enums\AuditAction;
use App\Enums\InviteRequestKind;
use App\Models\AuditEvent;
use App\Models\InviteRequest;
use App\Models\Tenant;
use App\Models\User;

/*
 * طلبُ الدعوة من صفحة التعريف — T-113.
 *
 * ولا تسجيلَ ذاتيّ في المنتج (SCREENS.md §1)، فهذا البابُ الوحيد لمن لا
 * حساب له. **ولا يُنشئ حساباً ولا جهة**: يقيّد طلباً يُقرأ يدوياً.
 */

it('records a lecture request from a visitor with no account', function (): void {
    $this->post('/invite', [
        'kind' => 'lecture',
        'name' => 'أبو بكر',
        'role' => 'مسجد أو مركز إسلامي',
        'contact' => 'someone@example.test',
        'link' => 'https://example.test/watch?v=abc',
    ])->assertRedirect()->assertSessionHas('invite_sent', trans('landing.invite.sent_lecture'));

    $request = InviteRequest::sole();

    expect($request->kind)->toBe(InviteRequestKind::Lecture)
        ->and($request->name)->toBe('أبو بكر')
        ->and($request->role)->toBe('مسجد أو مركز إسلامي')
        ->and($request->contact)->toBe('someone@example.test')
        ->and($request->link)->toBe('https://example.test/watch?v=abc')
        ->and($request->handled_at)->toBeNull();
});

/*
 * ── التبويبان — T-158، طلبُ مالك المنتج ────────────────────────────────
 *
 * «تواصل معنا» هو الافتراضيّ: اسمٌ ووسيلةُ تواصلٍ ورسالة، بلا صفةٍ ولا رابط.
 */

it('records a contact message with no role and no link', function (): void {
    $this->post('/invite', [
        'kind' => 'contact',
        'name' => 'زائر',
        'contact' => '+900000000000',
        'message' => 'كم تكلّف الخلاصة الواحدة؟',
    ])->assertRedirect()->assertSessionHasNoErrors()
        ->assertSessionHas('invite_sent', trans('landing.invite.sent_contact'));

    $request = InviteRequest::sole();

    expect($request->kind)->toBe(InviteRequestKind::Contact)
        ->and($request->role)->toBeNull()
        ->and($request->link)->toBeNull()
        ->and($request->message)->toBe('كم تكلّف الخلاصة الواحدة؟');
});

it('treats a request with no kind as a contact message, the default tab', function (): void {
    $this->post('/invite', ['name' => 'زائر', 'contact' => 'a@b.test', 'message' => 'سؤال.'])
        ->assertSessionHasNoErrors();

    expect(InviteRequest::sole()->kind)->toBe(InviteRequestKind::Contact);
});

it('drops a role or link sent along with a contact message', function (): void {
    $this->post('/invite', [
        'kind' => 'contact',
        'name' => 'زائر',
        'contact' => 'a@b.test',
        'message' => 'سؤال.',
        'role' => 'طالب أو باحث',
        'link' => 'https://example.test/x',
    ])->assertSessionHasNoErrors();

    expect(InviteRequest::sole()->role)->toBeNull()
        ->and(InviteRequest::sole()->link)->toBeNull();
});

it('needs the message in a contact message, because the message is the request', function (): void {
    $this->post('/invite', ['kind' => 'contact', 'name' => 'زائر', 'contact' => 'a@b.test'])
        ->assertSessionHasErrors(['message' => trans('landing.invite.errors.message_required')]);

    expect(InviteRequest::count())->toBe(0);
});

it('needs the lecture link in a lecture request, because the lecture is the request', function (): void {
    $this->post('/invite', [
        'kind' => 'lecture', 'name' => 'زائر', 'role' => 'طالب أو باحث', 'contact' => 'a@b.test',
    ])->assertSessionHasErrors(['link' => trans('landing.invite.errors.lecture_link')]);

    expect(InviteRequest::count())->toBe(0);
});

it('needs the role in a lecture request', function (): void {
    $this->post('/invite', [
        'kind' => 'lecture', 'name' => 'زائر', 'contact' => 'a@b.test', 'link' => 'https://example.test/x',
    ])->assertSessionHasErrors('role');
});

/*
 * رسالةُ صاحب الطلب — T-142، بلاغُ مالك المنتج.
 *
 * وما فوقها تصنيفٌ اخترناه له، وهذه كلماتُه: **الحقلُ الوحيد الذي يقول
 * ماذا يريد** لا من هو.
 */
it('keeps the words the sender wrote, newlines and all', function (): void {
    $written = "أُدير مركزاً في إسطنبول.\n\nهل تعملون على دروسٍ بالتركية؟";

    $this->post('/invite', [
        'kind' => 'contact',
        'name' => 'زائر',
        'contact' => 'someone@example.test',
        'message' => $written,
    ])->assertRedirect();

    // **بحرفه وأسطره**: فقرتان تُلصَقان في سطرٍ واحدٍ تُغيّران ما قيل.
    expect(InviteRequest::sole()->message)->toBe($written);
});

it('accepts a lecture request with no message, because there the message is a note', function (): void {
    $this->post('/invite', [
        'kind' => 'lecture',
        'name' => 'زائر',
        'role' => 'طالب أو باحث',
        'contact' => 'someone@example.test',
        'link' => 'https://example.test/x',
    ])->assertRedirect()->assertSessionHasNoErrors();

    expect(InviteRequest::sole()->message)->toBeNull();
});

it('refuses a message longer than the field takes, in the visitor language', function (): void {
    $this->post('/invite', [
        'kind' => 'contact',
        'name' => 'زائر',
        'contact' => 'someone@example.test',
        'message' => str_repeat('ا', 2001),
    ])->assertSessionHasErrors(['message' => trans('landing.invite.errors.message')]);

    expect(InviteRequest::count())->toBe(0);
});

/*
 * ★ **ولا جملةَ تُخبر عن حجمنا** — T-142، بلاغُ مالك المنتج: «we don't
 * need to share details like: والمنصّةُ اليوم في مرحلة دعواتٍ محدودة».
 *
 * فمن يقرأ «عددٌ معدود» يقرأ باباً قد يُغلق دونه، فيُحجم حيث أُريد أن
 * يُقدِم. **والمقيسُ الأربعُ لغات**: النصُّ عربيُّ الأصل وثلاثتُه تبعٌ له
 * (CLAUDE.md §1)، وحذفٌ في واحدةٍ وحدها يُبقيها في ثلاث.
 */
it('promises nothing about how few are let in', function (string $locale): void {
    $lede = trans('landing.invite.lede', [], $locale);

    foreach (['دعوات', 'limited invitation', 'sınırlı davet', 'ограниченных приглашений'] as $phrase) {
        expect($lede)->not->toContain($phrase);
    }

    // وما بقي تعهّدٌ لا تسويق: الحكمُ للقارئ، وهو جوهرُ العرض.
    expect(trim($lede))->not->toBe('');
})->with(['ar', 'en', 'tr', 'ru']);

it('refuses a request with no way to reach the sender', function (): void {
    $this->post('/invite', ['kind' => 'contact', 'name' => 'زائر', 'message' => 'سؤال.'])
        ->assertSessionHasErrors('contact');

    expect(InviteRequest::count())->toBe(0);
});

it('refuses a malformed link rather than storing it', function (): void {
    $this->post('/invite', [
        'kind' => 'lecture',
        'name' => 'زائر',
        'role' => 'طالب أو باحث',
        'contact' => 'someone@example.test',
        'link' => 'ليس رابطاً',
    ])->assertSessionHasErrors(['link' => trans('landing.invite.errors.link')]);

    expect(InviteRequest::count())->toBe(0);
});

it('says everything in Arabic, with no error code', function (string $kind): void {
    // SCREENS.md: «كل خطأ يذكر ما حدث ولماذا وما الإجراء. لا كود خطأ.»
    $errors = $this->post('/invite', ['kind' => $kind])->assertSessionHasErrors()
        ->getSession()->get('errors')->getBag('default')->all();

    expect($errors)->not->toBeEmpty();

    foreach ($errors as $message) {
        expect($message)->toMatch('/[\x{0600}-\x{06FF}]/u')
            ->and($message)->not->toMatch('/[A-Za-z]{4,}/');
    }
})->with(['contact', 'lecture']);

it('throttles the form, because it sits on the open root of the site', function (): void {
    $payload = ['kind' => 'contact', 'name' => 'زائر', 'contact' => 'a@b.test', 'message' => 'سؤال.'];

    for ($i = 0; $i < 10; $i++) {
        $this->post('/invite', $payload)->assertRedirect();
    }

    $this->post('/invite', $payload)->assertStatus(429);
});

/*
 * ── الصفحة — T-158 ─────────────────────────────────────────────────────
 */

it('opens on the contact tab, with the lecture tab beside it', function (): void {
    $html = $this->get('/')->assertOk()
        ->assertSee(__('landing.invite.tab_contact'))
        ->assertSee(__('landing.invite.tab_lecture'))
        ->getContent();

    expect($html)->toContain('id="panel-contact" role="tabpanel" aria-labelledby="tab-contact"')
        ->and($html)->toMatch('/id="tab-contact"[^>]*aria-selected="true"/')
        ->and($html)->toMatch('/id="panel-lecture"[^>]*data-off/')
        ->and($html)->not->toMatch('/id="panel-contact"[^>]*data-off/');
});

it('brings the visitor back to the lecture tab after a mistake there', function (): void {
    $this->from('/')->post('/invite', ['kind' => 'lecture', 'name' => 'زائر']);

    $html = $this->get('/')->getContent();

    expect($html)->toMatch('/id="tab-lecture"[^>]*aria-selected="true"/')
        ->and($html)->toMatch('/id="panel-contact"[^>]*data-off/')
        ->and($html)->toContain(trans('landing.invite.errors.lecture_link'))
        // والخطأ في تبويبه وحده: «الرسالة مطلوبة» لا تظهر في التواصل.
        ->and($html)->not->toContain(trans('landing.invite.errors.message_required'));
});

/*
 * ── شاشةُ المشرف — T-135 ───────────────────────────────────────────────
 *
 * ★ **والمقيسُ هنا أنّ ما وصل يُرى.** فالطلبُ كان يُكتب في القاعدة منذ
 * T-113 ولا مسارَ يقرؤه — وهي عينُ ثغرة T-28: الشكوى تُسجَّل ولا يراها
 * أحد. فاختبارُ الكتابة وحده كان أخضرَ والثغرةُ قائمة.
 */

it('shows what the public form collected, pending first', function (): void {
    $admin = User::factory()->superAdmin()->create();

    $old = InviteRequest::create([
        'name' => 'طلبٌ قديم معالَج', 'role' => 'مسجد', 'contact' => 'old@example.test',
    ]);
    $old->forceFill(['handled_at' => now()->subDay(), 'created_at' => now()->subWeek()])->save();

    InviteRequest::create([
        'name' => 'طلبٌ منتظِر', 'role' => 'مركز', 'contact' => 'new@example.test',
    ]);

    $response = $this->actingAs($admin, 'admin')->get('/admin/invites')->assertOk();

    $rows = $response->viewData('page')['props']['requests']['data'];

    // **المنتظِرُ يتصدّر ولو كان أحدثَ وصولاً** — صندوقٌ يُفرَّغ لا قائمةٌ تُقرأ.
    expect($rows[0]['name'])->toBe('طلبٌ منتظِر')
        ->and($rows[0]['handled_at'])->toBeNull()
        ->and($rows[1]['name'])->toBe('طلبٌ قديم معالَج')
        ->and($response->viewData('page')['props']['counts']['pending'])->toBe(1);
});

it('tells the admin which kind each request is, old rows being lecture requests', function (): void {
    $admin = User::factory()->superAdmin()->create();

    // صفٌّ بلا نوعٍ صريح كصفوف ما قبل T-158: الافتراضيُّ في العمود `lecture`.
    InviteRequest::create(['name' => 'قديم', 'role' => 'مسجد', 'contact' => 'a@b.test']);
    InviteRequest::create(['kind' => InviteRequestKind::Contact, 'name' => 'جديد', 'contact' => 'c@d.test', 'message' => 'سؤال.']);

    $rows = collect($this->actingAs($admin, 'admin')->get('/admin/invites')
        ->viewData('page')['props']['requests']['data'])->keyBy('name');

    expect($rows['قديم']['kind'])->toBe('lecture')
        ->and($rows['جديد']['kind'])->toBe('contact')
        ->and($rows['جديد']['role'])->toBeNull();
});

it('carries the message to the screen whole, not cut to a line', function (): void {
    $admin = User::factory()->superAdmin()->create();

    $written = "سطرٌ أوّل.\nسطرٌ ثانٍ.\n".str_repeat('كلامٌ مطوَّل. ', 60);

    InviteRequest::create([
        'name' => 'زائر', 'role' => 'مركز', 'contact' => 'a@b.test', 'message' => $written,
    ]);

    $row = $this->actingAs($admin, 'admin')->get('/admin/invites')
        ->viewData('page')['props']['requests']['data'][0];

    /*
     * ★ **ولا قصَّ في الخادم** — T-142. فهي الحقلُ الوحيد الذي كتبه صاحبُ
     * الطلب، وبترُها إلى سطرٍ يُخفي السببَ الذي من أجله أُضيف الحقل.
     */
    expect($row['message'])->toBe($written);
});

it('never exposes the visitor ip, which was collected to throttle not to track', function (): void {
    $admin = User::factory()->superAdmin()->create();

    InviteRequest::create([
        'name' => 'زائر', 'role' => 'طالب', 'contact' => 'a@b.test', 'ip' => '203.0.113.9',
    ]);

    $row = $this->actingAs($admin, 'admin')->get('/admin/invites')
        ->viewData('page')['props']['requests']['data'][0];

    expect($row)->not->toHaveKey('ip')
        ->and(json_encode($row))->not->toContain('203.0.113.9');
});

it('marks a request handled, and writes it to the admin audit log', function (): void {
    $admin = User::factory()->superAdmin()->create();
    $request = InviteRequest::create([
        'name' => 'أبو بكر', 'role' => 'مسجد', 'contact' => 'someone@example.test',
    ]);

    $this->actingAs($admin, 'admin')
        ->put("/admin/invites/{$request->id}", ['handled' => true])
        ->assertRedirect();

    expect($request->refresh()->handled_at)->not->toBeNull();

    $event = AuditEvent::query()->where('action', AuditAction::InviteRequestHandled->value)->sole();

    // اللقطةُ باسم الطالب، فيُقرأ السجلّ بعد أشهر بلا ضمٍّ إلى جدولٍ آخر.
    expect($event->subject_label)->toBe('أبو بكر')
        ->and($event->changes['handled_at']['from'])->toBeNull()
        ->and($event->changes['handled_at']['to'])->not->toBeNull();
});

it('lets a mistaken mark be undone, so one click does not bury a request', function (): void {
    $admin = User::factory()->superAdmin()->create();
    $request = InviteRequest::create([
        'name' => 'زائر', 'role' => 'طالب', 'contact' => 'a@b.test',
    ]);
    $request->forceFill(['handled_at' => now()])->save();

    $this->actingAs($admin, 'admin')
        ->put("/admin/invites/{$request->id}", ['handled' => false])
        ->assertRedirect();

    expect($request->refresh()->handled_at)->toBeNull();
});

it('does not record an action that did not happen', function (): void {
    $admin = User::factory()->superAdmin()->create();
    $request = InviteRequest::create([
        'name' => 'زائر', 'role' => 'طالب', 'contact' => 'a@b.test',
    ]);

    // مرّتان على «معالَج»: الثانيةُ لا تُبدّل حالاً، فلا تُقيَّد.
    $this->actingAs($admin, 'admin')->put("/admin/invites/{$request->id}", ['handled' => true]);
    $this->actingAs($admin, 'admin')->put("/admin/invites/{$request->id}", ['handled' => true]);

    expect(AuditEvent::query()->where('action', AuditAction::InviteRequestHandled->value)->count())->toBe(1);
});

it('hides the screen from a tenant user, as the guard hides the whole panel', function (): void {
    $tenant = Tenant::factory()->create();
    $user = User::factory()->for($tenant)->create();

    // ٤٠٤ لا ٤٠٣ — وجودُ اللوحة لا يُؤكَّد لمن لا يملكها.
    $this->actingAs($user, 'web')->get('/admin/invites')->assertNotFound();
    $this->get('/admin/invites')->assertNotFound();
});
