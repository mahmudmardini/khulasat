<?php

declare(strict_types=1);

use App\Models\QuranAyah;
use App\Models\QuranTranslation;
use App\Support\Quran\QuranSync;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

/*
 * T-161: المصحف وترجماته من Quran Foundation بحساب مطوّر، ومزامنةٌ أسبوعية.
 *
 * شروطُهم لا تُجيز حفظ المحتوى أكثر من أسبوع بلا مزامنة. **ولا نداء شبكة
 * هنا**: الواجهة كلّها مزيَّفة بـ`Http::fake`.
 */

/** عدد آيات كلّ سورة — فيُبنى مصحفٌ مزيَّفٌ بمفاتيحه الحقيقية الـ٦٢٣٦. */
const AYAH_COUNTS = [7, 286, 200, 176, 120, 165, 206, 75, 129, 109, 123, 111, 43, 52, 99, 128, 111, 110, 98, 135,
    112, 78, 118, 64, 77, 227, 93, 88, 69, 60, 34, 30, 73, 54, 45, 83, 182, 88, 75, 85, 54, 53, 89, 59, 37, 35, 38,
    29, 18, 45, 60, 49, 62, 55, 78, 96, 29, 22, 24, 13, 14, 11, 11, 18, 12, 12, 30, 52, 52, 44, 28, 28, 20, 56, 40,
    31, 50, 40, 46, 42, 29, 19, 36, 25, 22, 17, 19, 26, 30, 20, 15, 21, 11, 8, 8, 19, 5, 8, 8, 11, 11, 8, 3, 9, 5, 4,
    7, 3, 6, 3, 5, 4, 5, 6];

/** @return list<string> */
function verseKeys(): array
{
    $keys = [];

    foreach (AYAH_COUNTS as $index => $count) {
        for ($ayah = 1; $ayah <= $count; $ayah++) {
            $keys[] = ($index + 1).':'.$ayah;
        }
    }

    return $keys;
}

/**
 * @param  array<string, mixed>  $overrides  مسارٌ ← ردٌّ بديل.
 */
function fakeQuranFoundation(array $overrides = []): void
{
    $keys = verseKeys();

    $core = [];

    foreach (AYAH_COUNTS as $index => $count) {
        $core[] = ['record_type' => 'chapter', 'id' => $index + 1, 'chapter_number' => $index + 1, 'name_arabic' => 'سورة '.($index + 1)];
    }

    foreach ($keys as $id => $key) {
        $core[] = ['record_type' => 'verse', 'id' => $id + 1, 'verse_key' => $key, 'text_uthmani' => "عُثْمَانِيّ {$key}"];
    }

    $translation = static fn (int $id): array => [
        'resource_group' => 'translations',
        'resource_id' => $id,
        'records' => array_map(
            static fn (string $key): array => ['id' => 1, 'verse_key' => $key, 'text' => "Meaning {$key}<sup foot_note=\"9\">1</sup>"],
            $keys,
        ),
    ];

    Http::fake([
        'oauth2.quran.foundation/*' => Http::response(['access_token' => 'token-123', 'expires_in' => 3600]),
        'apis.quran.foundation/content/api/v4/resources/snapshots/quran_core/1' => $overrides['core']
            ?? Http::response(['resource_group' => 'quran_core', 'resource_id' => 1, 'records' => $core]),
        'apis.quran.foundation/content/api/v4/quran/verses/imlaei' => Http::response([
            'verses' => array_map(static fn (string $key): array => ['verse_key' => $key, 'text_imlaei' => "إِمْلائِي {$key}"], $keys),
        ]),
        'apis.quran.foundation/content/api/v4/resources/snapshots/translations/20' => $overrides['translation:20'] ?? Http::response($translation(20)),
        'apis.quran.foundation/content/api/v4/resources/snapshots/translations/45' => Http::response($translation(45)),
        'apis.quran.foundation/content/api/v4/resources/snapshots/translations/77' => Http::response($translation(77)),
    ]);
}

beforeEach(function (): void {
    config([
        'khulasah.quran.client_id' => 'client-abc',
        'khulasah.quran.client_secret' => 'secret-xyz',
    ]);
});

it('seeds the mushaf from the quran_core snapshot and the imlaei text, with the developer credentials', function (): void {
    fakeQuranFoundation();

    $this->artisan('khulasah:seed-quran')->assertSuccessful();

    $ayah = QuranAyah::query()->where('surah', 2)->where('ayah', 255)->sole();

    expect(QuranAyah::count())->toBe(6_236)
        ->and($ayah->text_uthmani)->toBe('عُثْمَانِيّ 2:255')
        ->and($ayah->text_imlaei)->toBe('إِمْلائِي 2:255')
        ->and($ayah->text_normalized)->toBe('املايي 2 255')
        ->and($ayah->surah_name_ar)->toBe('سورة 2')
        ->and(QuranSync::lastSynced(QuranSync::CORE))->not->toBeNull();

    Http::assertSent(fn (Request $request): bool => str_contains($request->url(), 'oauth2.quran.foundation')
        && $request->hasHeader('Authorization', 'Basic '.base64_encode('client-abc:secret-xyz'))
        && $request['grant_type'] === 'client_credentials'
        && $request['scope'] === 'content');

    Http::assertSent(fn (Request $request): bool => str_contains($request->url(), 'snapshots/quran_core/1')
        && $request->hasHeader('x-auth-token', 'token-123')
        && $request->hasHeader('x-client-id', 'client-abc'));

    // ولا نداءَ للواجهة القديمة بلا حساب.
    Http::assertNotSent(fn (Request $request): bool => str_contains($request->url(), 'api.quran.com'));
});

it('writes nothing when the core snapshot is incomplete', function (): void {
    fakeQuranFoundation(['core' => Http::response(['records' => [
        ['record_type' => 'verse', 'verse_key' => '1:1', 'text_uthmani' => 'بِسْمِ'],
    ]])]);

    $this->artisan('khulasah:seed-quran')->assertFailed();

    expect(QuranAyah::count())->toBe(0)
        ->and(QuranSync::lastSynced(QuranSync::CORE))->toBeNull();
});

it('refuses to run without the developer credentials, and says which key is missing', function (): void {
    config(['khulasah.quran.client_id' => '']);
    fakeQuranFoundation();

    expect(fn () => $this->artisan('khulasah:seed-quran')->run())
        ->toThrow(RuntimeException::class, 'QURAN_CLIENT_ID');

    expect(QuranAyah::count())->toBe(0);
});

it('seeds translations by verse key from their snapshots, without footnotes', function (): void {
    fakeQuranFoundation();

    $this->artisan('khulasah:seed-quran')->assertSuccessful();
    $this->artisan('khulasah:seed-quran-translations')->assertSuccessful();

    $row = QuranTranslation::query()->where('locale', 'en')->where('surah', 2)->where('ayah', 255)->sole();

    expect(QuranTranslation::query()->count())->toBe(3 * 6_236)
        ->and($row->text)->toBe('Meaning 2:255')
        ->and($row->translation_id)->toBe(20)
        ->and(QuranSync::stale())->toBe([]);
});

it('writes nothing for a translation whose snapshot has a key the mushaf lacks', function (): void {
    $records = array_map(static fn (string $key): array => ['verse_key' => $key, 'text' => 'x'], verseKeys());
    $records[0]['verse_key'] = '200:1';

    fakeQuranFoundation(['translation:20' => Http::response(['records' => $records])]);
    $this->artisan('khulasah:seed-quran')->assertSuccessful();

    $this->artisan('khulasah:seed-quran-translations', ['--locale' => 'en'])->assertFailed();

    expect(QuranTranslation::query()->where('locale', 'en')->count())->toBe(0);
});

it('syncs the mushaf and every translation in one command', function (): void {
    fakeQuranFoundation();

    $this->artisan('khulasah:sync-quran')->assertSuccessful();

    expect(QuranAyah::count())->toBe(6_236)
        ->and(QuranTranslation::query()->count())->toBe(3 * 6_236)
        ->and(QuranSync::stale())->toBe([]);
});

it('reports a sync older than seven days as stale', function (): void {
    fakeQuranFoundation();
    $this->artisan('khulasah:sync-quran')->assertSuccessful();

    DB::table('quran_syncs')->where('resource', QuranSync::translation(45))->update(['synced_at' => now()->subDays(8)]);

    expect(QuranSync::stale())->toBe([QuranSync::translation(45)]);
});

it('is scheduled at least twice a week', function (): void {
    $this->artisan('schedule:list')->expectsOutputToContain('30 3 * * 1,4  php artisan khulasah:sync-quran');
});

it('blocks preflight when a translation is incomplete, and warns when the sync is stale', function (): void {
    $this->artisan('khulasah:preflight')
        ->expectsOutputToContain('الإنجليزية ناقصة')
        ->expectsOutputToContain('لم يُزامَن في الأيام السبعة الأخيرة');
});
