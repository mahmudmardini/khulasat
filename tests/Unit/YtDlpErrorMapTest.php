<?php

declare(strict_types=1);

use App\Enums\TranscriptErrorCode;
use App\Support\Transcript\YtDlpErrorMap;

// المواصفة §5-أ-6 البند ٣: «أكواد خطأ مستقلّة ورسائل مفهومة، لا فشل التفريغ».
// والرسائل هنا بنصّها كما يُخرجها yt-dlp، لا مصوغةً — فالمطابقة تُختبر على
// ما سيصلها فعلاً.

it('reads the bot check that datacenter addresses hit', function (string $stderr): void {
    expect(YtDlpErrorMap::forStderr($stderr))->toBe(TranscriptErrorCode::BotCheck);
})->with([
    "ERROR: [youtube] dQw4w9WgXcQ: Sign in to confirm you're not a bot. Use --cookies-from-browser or --cookies for the authentication.",
    // يوتيوب يكتبها فعلياً بفاصلة مقوَّسة (’) لا مستقيمة — اكتُشف على
    // نداء حقيقي، 14 أيلول 2026. والاختبار أعلاه بفاصلة مستقيمة لم يكشف
    // الفارق، فمرّ العطل صامتاً إلى `transcription_failed`.
    "ERROR: [youtube] VwthjTFnglE: Sign in to confirm you\u{2019}re not a bot. Use --cookies-from-browser or --cookies for the authentication.",
    'ERROR: unable to download API page: HTTP Error 429: Too Many Requests',
    'ERROR: [youtube] abc: HTTP Error 403: Forbidden',
]);

it('reads the codes that mean a video needs an account', function (string $stderr): void {
    expect(YtDlpErrorMap::forStderr($stderr))->toBe(TranscriptErrorCode::VideoPrivate);
})->with([
    "ERROR: [youtube] abc: Private video. Sign in if you've been granted access to this video",
    "ERROR: [youtube] abc: This video is available to this channel's members on level: صفوة. Join this channel to get access to members-only content and other exclusive perks.",
    "ERROR: [youtube] abc: This video is age-restricted and can't be watched without signing in.",
]);

// **أهمّ اختبار في هذا الملفّ.** رسالة الحجب الجغرافي تحوي «Video unavailable»
// بنصّها، فلو فُحص التعذّر العامّ قبلها صار كلّ حجب «فيديو غير موجود»،
// وأُرسل المستخدم يبحث عن رابط سليم بين يديه.
it('tells a geographic block from a missing video, though the text overlaps', function (string $stderr): void {
    expect(YtDlpErrorMap::forStderr($stderr))->toBe(TranscriptErrorCode::GeoBlocked);
})->with([
    'ERROR: [youtube] abc: The uploader has not made this video available in your country',
    'ERROR: [youtube] abc: Video unavailable. This video is not available in your country',
    'ERROR: [youtube] abc: Video unavailable. This video contains content from SME, who has blocked it in your country on copyright grounds.',
]);

it('reads a video that is gone', function (string $stderr): void {
    expect(YtDlpErrorMap::forStderr($stderr))->toBe(TranscriptErrorCode::VideoUnavailable);
})->with([
    'ERROR: [youtube] abc: Video unavailable',
    'ERROR: [youtube] abc: This video has been removed by the uploader',
    "ERROR: [youtube] abc: This video has been removed for violating YouTube's Terms of Service",
    'ERROR: Unsupported URL: https://www.youtube.com/feed/subscriptions',
    'ERROR: [youtube:truncated_id] abc: Incomplete YouTube ID abc.',
]);

it('reads a timeout reported by yt-dlp itself', function (): void {
    expect(YtDlpErrorMap::forStderr('ERROR: Unable to download webpage: The read operation timed out'))
        ->toBe(TranscriptErrorCode::YtdlpTimeout);
});

// عطلٌ لم نعرفه بعدُ لا يُترك بلا رمز: الافتراضي رسالته تقترح إعادةً ثم
// المسار اليدوي، فلا يقف مدير المحتوى أمام شيء لا إجراء له.
it('falls back to a failure that still suggests an action', function (string $stderr): void {
    expect(YtDlpErrorMap::forStderr($stderr))->toBe(TranscriptErrorCode::TranscriptionFailed);
})->with([
    'ERROR: something entirely new that yt-dlp has never said before',
    '',
    'WARNING: Falling back to generic extractor',
]);

it('matches whatever the letter case', function (): void {
    expect(YtDlpErrorMap::forStderr('PRIVATE VIDEO'))->toBe(TranscriptErrorCode::VideoPrivate)
        ->and(YtDlpErrorMap::forStderr('Private Video'))->toBe(TranscriptErrorCode::VideoPrivate);
});

// الحجب وحده يُحوَّل إلى المسار اليدوي — §5-أ-6 البند ١. والخاصّ والمحجوب
// جغرافياً يُصلحهما صاحب الفيديو، فلا معنى لتحويلهما.
it('hands only the operational failures to the manual path', function (): void {
    expect(TranscriptErrorCode::BotCheck->fallsBackToManualPath())->toBeTrue()
        ->and(TranscriptErrorCode::TranscriptionFailed->fallsBackToManualPath())->toBeTrue()
        ->and(TranscriptErrorCode::YtdlpTimeout->fallsBackToManualPath())->toBeTrue()
        ->and(TranscriptErrorCode::VideoPrivate->fallsBackToManualPath())->toBeFalse()
        ->and(TranscriptErrorCode::GeoBlocked->fallsBackToManualPath())->toBeFalse()
        ->and(TranscriptErrorCode::HostNotAllowed->fallsBackToManualPath())->toBeFalse()
        ->and(TranscriptErrorCode::DurationExceeded->fallsBackToManualPath())->toBeFalse();
});

// المواصفة §5-أ-7: عشرة أكواد، لكلّ واحد رسالة عربية تقترح إجراءً،
// **ولا يظهر الرمز في رسالته**.
it('gives every one of the ten codes an Arabic message that is not its code', function (): void {
    $codes = TranscriptErrorCode::cases();

    expect($codes)->toHaveCount(10);

    foreach ($codes as $code) {
        expect($code->message())
            ->not->toBe('errors.transcript.'.$code->value)
            ->not->toContain($code->value)
            ->and(preg_match('/\p{Arabic}/u', $code->message()))->toBe(1);
    }
});
