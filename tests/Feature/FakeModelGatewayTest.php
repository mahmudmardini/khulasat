<?php

declare(strict_types=1);

use App\Contracts\ModelGateway;
use App\Enums\Stage;
use App\Exceptions\ModelCallFailed;
use App\Models\SummaryJob;
use App\Models\Tenant;
use App\Services\Model\DatabaseModelGateway;
use App\Services\Model\FakeModelGateway;
use Illuminate\Support\Facades\Http;

// T-10ج: «لا نداء حقيقي في CI إطلاقاً». والوهمية هي الافتراضية،
// وتبديلها يحتاج MODEL_GATEWAY=real صريحاً — CLAUDE.md §2 القاعدة السابعة.

beforeEach(function (): void {
    Http::preventStrayRequests();

    $this->tenant = Tenant::factory()->create();
    $this->job = SummaryJob::factory()->create(['tenant_id' => $this->tenant->id]);
});

function fake_(): FakeModelGateway
{
    return app(FakeModelGateway::class);
}

// ── الاختيار: الوهمية افتراضاً ───────────────────────────────────

// **أهمّ اختبار هنا.** لو انقلب الافتراض لأنفق نسيانُ الإعداد مالاً.
it('resolves the fake gateway by default', function (): void {
    expect(app(ModelGateway::class))->toBeInstanceOf(FakeModelGateway::class);
});

it('opens the real gateway only for an explicit real setting', function (): void {
    config()->set('khulasah.model.gateway', 'real');
    app()->forgetInstance(ModelGateway::class);

    expect(app(ModelGateway::class))->toBeInstanceOf(DatabaseModelGateway::class);
});

// أيّ قيمة أخرى تُبقي الوهمية: الإعداد الناقص أو المكتوب خطأً لا يفتح الباب.
it('keeps the fake for any other value', function (?string $value): void {
    config()->set('khulasah.model.gateway', $value);
    app()->forgetInstance(ModelGateway::class);

    expect(app(ModelGateway::class))->toBeInstanceOf(FakeModelGateway::class);
})->with([null, '', 'fake', 'REAL_BUT_TYPO', 'production']);

// ويبقى العقد محروساً: تُصدي كلَّ مفتاحٍ وصلها، ولا تخترع مفتاحاً.
it('يُصدي جدولَ الترجمة بمفاتيحه ولا يزيد', function (): void {
    $payload = json_encode([
        'target_locale' => 'tr',
        'strings' => [['key' => 'a.b', 'text' => 'نصّ'], ['key' => 'c', 'text' => 'آخر']],
    ], JSON_UNESCAPED_UNICODE);

    $decoded = fake_()->call(Stage::Translating, [['role' => 'user', 'content' => (string) $payload]])->decoded;

    expect($decoded['translations'])->toHaveCount(2)
        ->and($decoded['translations'][0]['key'])->toBe('a.b')
        ->and($decoded['translations'][0]['text'])->toBe('[tr] نصّ');
});

// ── يتحقّق من المخطّط كما تفعل الحقيقية ──────────────────────────

// **ولو تساهل لصارت الاختبارات تمرّ على مخرَجٍ لا يمرّ في الإنتاج**، وهو
// أسوأ من لا اختبار.
it('validates the schema exactly as the real gateway does', function (): void {
    $schema = ['type' => 'object', 'required' => ['nonexistent_key']];

    expect(fn () => fake_()->call(Stage::ExtractingStructure, [['role' => 'user', 'content' => 'x']], $schema))
        ->toThrow(ModelCallFailed::class);
});

it('replays an output that breaks the schema', function (): void {
    $gateway = fake_();
    $gateway->willReturn(Stage::ExtractingEvidence, 'schema_mismatch');

    $schema = ['type' => 'object', 'required' => ['evidence'], 'properties' => [
        'evidence' => ['type' => 'array', 'items' => [
            'type' => 'object',
            'properties' => ['kind' => ['type' => 'string', 'enum' => ['ayah', 'hadith']]],
        ]],
    ]];

    expect(fn () => $gateway->call(Stage::ExtractingEvidence, [['role' => 'user', 'content' => 'x']], $schema))
        ->toThrow(ModelCallFailed::class);
});

it('replays the transport failures, with the right retry class', function (string $case, string $code, bool $retryable): void {
    $gateway = fake_();
    $gateway->willReturn(Stage::Cleaning, $case);

    try {
        $gateway->call(Stage::Cleaning, [['role' => 'user', 'content' => 'x']]);
    } catch (ModelCallFailed $failure) {
        expect($failure->errorCode)->toBe($code)
            ->and($failure->retryable)->toBe($retryable);

        return;
    }

    $this->fail('كان يجب أن يُرفع عطل.');
})->with([
    ['rate_limited', 'rate_limited', true],
    ['provider_error', 'provider_error', true],
    ['content_rejected', 'content_rejected', false],
    ['authentication_failed', 'authentication_failed', false],
]);

// **لا يُخترع ردّ.** ردٌّ مصنوع في الطيران يجعل الاختبار يقيس خيالَنا لا
// مخرَج النموذج، ويُخفي أنّ العيّنة ناقصة.
it('refuses to invent a response that was never recorded', function (): void {
    $gateway = fake_();
    $gateway->willReturn(Stage::Writing, 'case_that_does_not_exist');

    try {
        $gateway->call(Stage::Writing, [['role' => 'user', 'content' => 'x']]);
    } catch (ModelCallFailed $failure) {
        expect($failure->errorCode)->toBe('fixture_missing')
            ->and($failure->getMessage())->toContain('khulasah:record-response');

        return;
    }

    $this->fail('كان يجب أن يقف بلا عيّنة.');
});

// ── أمر التسجيل ─────────────────────────────────────────────────

it('refuses to record without user content', function (): void {
    $this->artisan('khulasah:record-response', ['stage' => 'cleaning'])->assertFailed();
});

it('refuses an unknown stage', function (): void {
    $this->artisan('khulasah:record-response', ['stage' => 'not_a_stage'])->assertFailed();
});

// ولا يكتب فوق ردٍّ محفوظ بلا --force: العيّنة المسجّلة عملٌ يُفقد بلمسة.
it('refuses to overwrite a recorded response without force', function (): void {
    $file = sys_get_temp_dir().'/khulasah-record-'.bin2hex(random_bytes(4)).'.txt';
    file_put_contents($file, 'نصّ');

    $this->artisan('khulasah:record-response', [
        'stage' => 'cleaning',
        'case' => 'default',
        '--file' => $file,
    ])->assertFailed();

    Http::assertNothingSent();

    unlink($file);
});
