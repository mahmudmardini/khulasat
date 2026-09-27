<?php

declare(strict_types=1);

use App\Support\Transcript\Preflight;

// المواصفة §5-أ-1 و§5-أ-2. الأشكال هنا تحاكي مخرَج yt-dlp --dump-json
// الحقيقي، ومنه ما يُوقع: يوتيوب يعرض ترجمةً عربيةً آليّةً لكلّ فيديو
// تقريباً، وأكثرها ترجمةُ نصٍّ إنجليزيّ لا نصٌّ عربيّ.

/** @param  array<string, mixed>  $overrides */
function dumpJson(array $overrides = []): array
{
    return [
        'title' => 'شرح كتاب الطهارة — الدرس الأول',
        'duration' => 3_600,
        'channel' => 'قناة الدروس',
        'upload_date' => '20260901',
        'language' => 'ar',
        'subtitles' => [],
        'automatic_captions' => [],
        ...$overrides,
    ];
}

/** مسارٌ أصليّ: عنوانه بلا معامل ترجمة. */
function originalTrack(string $ext = 'vtt'): array
{
    return [['ext' => $ext, 'url' => 'https://www.youtube.com/api/timedtext?v=abc&lang=ar', 'name' => 'Arabic']];
}

/** مسارٌ مترجَم آلياً: `tlang=` هو معامل الترجمة عند يوتيوب. */
function translatedTrack(string $ext = 'vtt'): array
{
    return [['ext' => $ext, 'url' => 'https://www.youtube.com/api/timedtext?v=abc&lang=en&tlang=ar', 'name' => 'Arabic']];
}

it('reads the fields the preflight decides on', function (): void {
    $preflight = Preflight::fromDumpJson(dumpJson());

    expect($preflight->durationSeconds)->toBe(3_600)
        ->and($preflight->title)->toBe('شرح كتاب الطهارة — الدرس الأول')
        ->and($preflight->channel)->toBe('قناة الدروس')
        ->and($preflight->uploadDate)->toBe('20260901')
        ->and($preflight->isPlaylist)->toBeFalse();
});

it('survives a dump with none of its optional fields', function (): void {
    $preflight = Preflight::fromDumpJson([]);

    expect($preflight->durationSeconds)->toBeNull()
        ->and($preflight->arabicTrack())->toBeNull()
        ->and($preflight->exceedsLimitMinutes(60))->toBeFalse();
});

// المواصفة §5-أ-1 الفحص الثاني و§11.
it('rejects a lecture longer than the tenant limit', function (): void {
    // ٧٢٦٠ ثانية = ١٢١ دقيقة بالضبط.
    $preflight = Preflight::fromDumpJson(dumpJson(['duration' => 7_260]));

    expect($preflight->exceedsLimitMinutes(60))->toBeTrue()
        ->and($preflight->exceedsLimitMinutes(120))->toBeTrue()
        ->and($preflight->exceedsLimitMinutes(121))->toBeFalse()
        ->and($preflight->exceedsLimitMinutes(180))->toBeFalse();
});

it('treats an exact match on the limit as within it', function (): void {
    expect(Preflight::fromDumpJson(dumpJson(['duration' => 3_600]))->exceedsLimitMinutes(60))
        ->toBeFalse();
});

// مدّة مجهولة لا تُعدّ تجاوزاً: الرفض يحتاج دليلاً.
it('does not reject a lecture whose duration is unknown', function (): void {
    expect(Preflight::fromDumpJson(dumpJson(['duration' => null]))->exceedsLimitMinutes(1))
        ->toBeFalse();
});

// جدول §5-أ-2: اليدويّة أوّلاً، فهي مكتوبة بيد إنسان.
it('prefers a manual Arabic subtitle over an automatic one', function (): void {
    $track = Preflight::fromDumpJson(dumpJson([
        'subtitles' => ['ar' => originalTrack()],
        'automatic_captions' => ['ar' => originalTrack()],
    ]))->arabicTrack();

    expect($track)->not->toBeNull()
        ->and($track->isManual())->toBeTrue()
        ->and($track->languageCode)->toBe('ar');
});

it('takes the automatic Arabic caption when there is no manual one', function (): void {
    $track = Preflight::fromDumpJson(dumpJson([
        'automatic_captions' => ['ar' => originalTrack()],
    ]))->arabicTrack();

    expect($track)->not->toBeNull()
        ->and($track->isAutomatic)->toBeTrue();
});

it('accepts regional Arabic and the original-track suffix', function (string $code): void {
    $track = Preflight::fromDumpJson(dumpJson([
        'subtitles' => [$code => originalTrack()],
    ]))->arabicTrack();

    expect($track)->not->toBeNull()->and($track->languageCode)->toBe($code);
})->with(['ar', 'ar-SA', 'ar-EG', 'ar-orig']);

// المواصفة §5-أ-2: «لا تُقبل ترجمة بلغة غير العربية».
it('never takes a track in another language', function (): void {
    expect(Preflight::fromDumpJson(dumpJson([
        'subtitles' => ['en' => originalTrack(), 'fr' => originalTrack(), 'ur' => originalTrack()],
        'automatic_captions' => ['en' => originalTrack(), 'tr' => originalTrack()],
    ]))->arabicTrack())->toBeNull();
});

// عربيةٌ بحرف لاتيني: نقلٌ صوتيّ لا نصّ عربي، ولا يُطابَق به شاهد.
it('rejects Arabic transliterated into Latin script', function (): void {
    expect(Preflight::fromDumpJson(dumpJson([
        'subtitles' => ['ar-Latn' => originalTrack()],
    ]))->arabicTrack())->toBeNull();
});

// **أهمّ اختبار في هذا الملفّ** — المواصفة §5-أ-2: «ولا ترجمة مترجَمة آلياً
// عن لغة أخرى». يوتيوب يعرض «ar» لدرسٍ إنجليزي، وهي ترجمةُ نصّ التعرّف
// الآلي. ولو أُخذت لخرجت ألفاظ الأحاديث مترجمةً عن ترجمة.
it('refuses an automatic Arabic track that is a machine translation', function (): void {
    $preflight = Preflight::fromDumpJson(dumpJson([
        'language' => 'en',
        'automatic_captions' => [
            'en' => originalTrack(),
            'ar' => translatedTrack(),
        ],
    ]));

    expect($preflight->arabicTrack())->toBeNull();
});

it('still takes the original Arabic track when translations sit beside it', function (): void {
    $preflight = Preflight::fromDumpJson(dumpJson([
        'language' => 'ar',
        'automatic_captions' => [
            'ar' => originalTrack(),
            'en' => translatedTrack(),
            'fr' => translatedTrack(),
        ],
    ]));

    expect($preflight->arabicTrack())->not->toBeNull()
        ->and($preflight->arabicTrack()->isAutomatic)->toBeTrue();
});

// ترجمةٌ يدويّة عربية تُقبل وإن كانت لغة الفيديو غير عربية: إنسانٌ كتبها
// لهذا الفيديو، ولا يُفترض فيها أنّها ترجمةُ آلة.
it('accepts a manual Arabic subtitle on a non-Arabic video', function (): void {
    $track = Preflight::fromDumpJson(dumpJson([
        'language' => 'en',
        'subtitles' => ['ar' => originalTrack()],
    ]))->arabicTrack();

    expect($track)->not->toBeNull()->and($track->isManual())->toBeTrue();
});

// بلا عنوانٍ يُفحَص تبقى لغة الفيديو قرينةً وحدها.
it('falls back to the declared video language when tracks carry no url', function (): void {
    $withoutUrls = [['ext' => 'vtt', 'name' => 'Arabic']];

    expect(Preflight::fromDumpJson(dumpJson([
        'language' => 'en',
        'automatic_captions' => ['ar' => $withoutUrls],
    ]))->arabicTrack())->toBeNull();

    expect(Preflight::fromDumpJson(dumpJson([
        'language' => 'ar',
        'automatic_captions' => ['ar' => $withoutUrls],
    ]))->arabicTrack())->not->toBeNull();
});

// وغياب اللغة لا يُعدّ ترجمةً: الرفض يحتاج دليلاً.
it('does not assume translation when neither url nor language says so', function (): void {
    expect(Preflight::fromDumpJson([
        'automatic_captions' => ['ar' => [['ext' => 'vtt', 'name' => 'Arabic']]],
    ])->arabicTrack())->not->toBeNull();
});

it('prefers srt then vtt among the formats offered', function (): void {
    $track = Preflight::fromDumpJson(dumpJson([
        'subtitles' => ['ar' => [
            ['ext' => 'json3', 'url' => 'https://example.test/a.json3'],
            ['ext' => 'vtt', 'url' => 'https://example.test/a.vtt'],
            ['ext' => 'srt', 'url' => 'https://example.test/a.srt'],
        ]],
    ]))->arabicTrack();

    expect($track->ext)->toBe('srt');

    $vttOnly = Preflight::fromDumpJson(dumpJson([
        'subtitles' => ['ar' => [
            ['ext' => 'json3', 'url' => 'https://example.test/a.json3'],
            ['ext' => 'vtt', 'url' => 'https://example.test/a.vtt'],
        ]],
    ]))->arabicTrack();

    expect($vttOnly->ext)->toBe('vtt');
});

// المواصفة §5-أ-1 الفحص الثالث.
it('recognises a playlist dump', function (): void {
    expect(Preflight::fromDumpJson(['_type' => 'playlist'])->isPlaylist)->toBeTrue()
        ->and(Preflight::fromDumpJson(['_type' => 'video'])->isPlaylist)->toBeFalse();
});
