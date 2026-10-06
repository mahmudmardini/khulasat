<?php

declare(strict_types=1);

use App\Models\Lecture;
use App\Models\SummaryJob;
use App\Models\Tenant;
use App\Models\User;
use App\Support\Transcript\SourceKey;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Queue;

/*
 * حارسُ المصدر المكرَّر — T-65.
 *
 * **بلاغُ تشغيلٍ حقيقيّ:** أُنشئت ثلاثُ محاضرات من فيديو يوتيوب واحد،
 * إحداها بفارق ثلاثٍ وستّين ثانية عن أختها — فجرى الخطُّ ثلاثاً وصُرف ثمنُه
 * ثلاثاً، **ولم يعلم صاحبُه إلّا حين فتح قائمة المهامّ**.
 *
 * ★ **ولا يمنع، بل يقف ويسأل**: إعادةُ التوليد من الفيديو نفسه بلغةٍ أخرى
 * أو بقالبٍ آخر حاجةٌ مشروعة، والتكرارُ الصامت ليس كذلك.
 */

beforeEach(function (): void {
    Queue::fake();

    $this->tenant = Tenant::factory()->create();
    $this->user = User::factory()->owner()->for_($this->tenant)->create();
});

/** مدخلُ محاضرةٍ من رابط، بأقلّ ما تقبله الشاشة. */
function lecturePayload(string $url, array $extra = []): array
{
    return [
        'source_kind' => 'url',
        'source_url' => $url,
        'title_ar' => 'درس',
        'speaker_name' => 'الملقي',
        'venue_mode' => 'institution',
        ...$extra,
    ];
}

it('يوحّد صور رابط يوتيوب في مفتاحٍ واحد', function (): void {
    $key = SourceKey::for('https://www.youtube.com/watch?v=abc12345678');

    expect($key)->toBe('yt:abc12345678')
        // نفسُ الفيديو بصورٍ مختلفة — وهي التي مرّت فعلاً فتكرّر الصرف.
        ->and(SourceKey::for('https://www.youtube.com/watch?si=Abc123&v=abc12345678'))->toBe($key)
        ->and(SourceKey::for('https://youtu.be/abc12345678?t=90'))->toBe($key)
        ->and(SourceKey::for('https://www.youtube.com/shorts/abc12345678'))->toBe($key);
});

// **وما ليس يوتيوب يُقارَن برابطه**، ولا يُتوسَّع في التطبيع: تطبيعٌ متحمّس
// يجمع مصدرين مختلفين فيمنع محاضرةً مشروعة.
it('لا يجمع مصدرين مختلفين', function (): void {
    expect(SourceKey::for('https://example.com/a'))
        ->not->toBe(SourceKey::for('https://example.com/b'))
        ->and(SourceKey::for(null))->toBeNull()
        ->and(SourceKey::for('   '))->toBeNull();
});

it('يقف عند مصدرٍ أُدخل من قبلُ ويسمّي المحاضرة', function (): void {
    Lecture::factory()->create([
        'tenant_id' => $this->tenant->id,
        'title_ar' => 'عنوان الدرس',
        'source_url' => 'https://www.youtube.com/watch?v=abc12345678',
        'source_key' => 'yt:abc12345678',
    ]);

    // صورةٌ أخرى للرابط نفسه: الحارسُ يقارن المعرّف لا النصّ.
    $this->actingAs($this->user)
        ->post('/panel/lectures', lecturePayload('https://youtu.be/abc12345678'))
        ->assertSessionHasErrors('confirm_duplicate');

    expect(Lecture::query()->count())->toBe(1);
});

// **الإقرارُ يمضي**: الحاجزُ سؤالٌ لا منع.
it('يمضي حين يُقرّ المستخدم بالتكرار', function (): void {
    Lecture::factory()->create([
        'tenant_id' => $this->tenant->id,
        'source_url' => 'https://www.youtube.com/watch?v=abc12345678',
        'source_key' => 'yt:abc12345678',
    ]);

    $this->actingAs($this->user)
        ->post('/panel/lectures', lecturePayload('https://youtu.be/abc12345678', ['confirm_duplicate' => true]))
        ->assertSessionHasNoErrors();

    expect(Lecture::query()->count())->toBe(2)
        // والمفتاحُ يُكتب للجديدة كذلك، فيُكشف ثالثٌ لو جاء.
        ->and(Lecture::query()->latest('id')->first()->source_key)->toBe('yt:abc12345678');
});

// **العزلُ بالجهة**: مصدرُ جهةٍ لا يحجب جهةً أخرى.
it('لا يحجب جهةً بمصدر جهةٍ أخرى', function (): void {
    $other = Tenant::factory()->create();

    Lecture::factory()->create([
        'tenant_id' => $other->id,
        'source_url' => 'https://www.youtube.com/watch?v=abc12345678',
        'source_key' => 'yt:abc12345678',
    ]);

    $this->actingAs($this->user)
        ->post('/panel/lectures', lecturePayload('https://www.youtube.com/watch?v=abc12345678'))
        ->assertSessionHasNoErrors();
});

/*
 * ★ **والفحصُ المسبق يقوله قبل الإرسال** — T-226. كان التنبيه لا يظهر إلّا
 * بعد «ابدأ الإعداد» وتأكيد الكلفة، فيُردّ الطلب ويقفز المستخدم إلى صندوقٍ
 * لم يره. والفحصُ يسمّي المحاضرة ويدلّ على ملخّصها.
 */
it('يسمّي الفحصُ المسبق المحاضرةَ السابقة ويدلّ على ملخّصها', function (): void {
    Process::fake(['*' => Process::result(json_encode(['title' => 'درس', 'duration' => 600]))]);

    $lecture = Lecture::factory()->create([
        'tenant_id' => $this->tenant->id,
        'title_ar' => 'عنوان الدرس',
        'source_url' => 'https://www.youtube.com/watch?v=abc12345678',
        'source_key' => 'yt:abc12345678',
    ]);
    $job = SummaryJob::factory()->for_($lecture)->create();

    $this->actingAs($this->user)
        ->postJson('/panel/lectures/preflight', ['source_url' => 'https://youtu.be/abc12345678'])
        ->assertOk()
        ->assertJson(['duplicate' => ['title' => 'عنوان الدرس', 'url' => route('jobs.show', $job)]]);
});

it('لا يقول الفحصُ تكراراً لمصدرٍ جديد ولا لمصدر جهةٍ أخرى', function (): void {
    Process::fake(['*' => Process::result(json_encode(['title' => 'درس', 'duration' => 600]))]);

    Lecture::factory()->create([
        'tenant_id' => Tenant::factory()->create()->id,
        'source_url' => 'https://www.youtube.com/watch?v=abc12345678',
        'source_key' => 'yt:abc12345678',
    ]);

    $this->actingAs($this->user)
        ->postJson('/panel/lectures/preflight', ['source_url' => 'https://youtu.be/abc12345678'])
        ->assertOk()
        ->assertJson(['duplicate' => null]);
});
