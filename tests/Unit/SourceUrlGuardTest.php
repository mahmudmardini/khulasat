<?php

declare(strict_types=1);

use App\Enums\TranscriptErrorCode;
use App\Exceptions\TranscriptFailed;
use App\Support\Transcript\SourceUrlGuard;

// المواصفة §12 المخطر الثالث: الرابط يأتي من المستخدم ويُمرَّر إلى عملية
// تُجري نداءً شبكياً. وهذه الاختبارات هي ما يمنع أن يصير المنتج بوّابةً
// إلى شبكة الخادم الداخلية.

/** @param  list<string>  $hosts */
function withAllowedHosts(array $hosts): void
{
    config()->set('khulasah.transcript.allowed_hosts', $hosts);
}

beforeEach(function (): void {
    withAllowedHosts(['youtube.com', 'youtu.be']);
});

it('allows the platforms on the list', function (string $url): void {
    SourceUrlGuard::assertAllowed($url);

    expect(true)->toBeTrue();
})->with([
    'https://www.youtube.com/watch?v=abc123',
    'https://youtube.com/watch?v=abc123',
    'https://m.youtube.com/watch?v=abc123',
    'https://music.youtube.com/watch?v=abc123',
    'https://youtu.be/abc123',
    'http://www.youtube.com/watch?v=abc123',
]);

it('rejects a host that is not on the list', function (string $url): void {
    expect(fn () => SourceUrlGuard::assertAllowed($url))
        ->toThrow(TranscriptFailed::class);
})->with([
    'https://vimeo.com/123456',
    'https://example.com/lecture.mp4',
    'https://drive.google.com/file/d/abc/view',
]);

// **أخطر صنف في هذا الملفّ.** اللاحقة المتشابهة تمرّ على كلّ فحص يستعمل
// `str_contains` أو `str_ends_with` بلا حدّ نقطة، وهو الخطأ الشائع.
it('rejects a look-alike host', function (string $url): void {
    expect(fn () => SourceUrlGuard::assertAllowed($url))
        ->toThrow(TranscriptFailed::class);
})->with([
    'https://youtube.com.evil.com/watch?v=abc',
    'https://notyoutube.com/watch?v=abc',
    'https://evil-youtube.com/watch?v=abc',
    'https://youtu.be.evil.com/abc',
    'https://myyoutu.be/abc',
]);

// `https://youtube.com@127.0.0.1/` — المضيف الحقيقي ما بعد @، والعين تقرأ
// ما قبلها. وهي طريقة معروفة لتجاوز قائمة السماح.
it('rejects credentials smuggled before the host', function (): void {
    expect(fn () => SourceUrlGuard::assertAllowed('https://youtube.com@127.0.0.1/watch?v=a'))
        ->toThrow(TranscriptFailed::class)
        ->and(fn () => SourceUrlGuard::assertAllowed('https://youtube.com:pass@10.0.0.1/'))
        ->toThrow(TranscriptFailed::class);
});

// المواصفة §12: رفض العناوين الداخلية والنطاقات المحلّية.
it('rejects internal addresses and local domains', function (string $url): void {
    expect(fn () => SourceUrlGuard::assertAllowed($url))
        ->toThrow(TranscriptFailed::class);
})->with([
    'http://127.0.0.1/',
    'http://localhost/',
    'http://localhost:8000/watch?v=a',
    'http://10.0.0.5/',
    'http://172.16.0.1/',
    'http://192.168.1.1/',
    // بيانات وصف السحابة — الهدف الأوّل لكلّ SSRF على خادم سحابي.
    'http://169.254.169.254/latest/meta-data/',
    'http://[::1]/',
    'http://[fd00::1]/',
    'http://redis.internal/',
    'http://db.local/',
    'http://app.localdomain/',
]);

// صور العنوان المموّهة: كلّها 127.0.0.1، ويقبلها كثير من المحلّلين.
it('rejects disguised numeric forms of an address', function (string $url): void {
    expect(fn () => SourceUrlGuard::assertAllowed($url))
        ->toThrow(TranscriptFailed::class);
})->with([
    'http://2130706433/',
    'http://0x7f000001/',
    'http://0177.0.0.1/',
    'http://127.1/',
]);

// المخطّط غير http(s) يفتح قراءة ملفّات الخادم ومخاطبة خدماته.
it('rejects schemes other than http and https', function (string $url): void {
    expect(fn () => SourceUrlGuard::assertAllowed($url))
        ->toThrow(TranscriptFailed::class);
})->with([
    'file:///etc/passwd',
    'gopher://youtube.com/',
    'dict://youtube.com:11211/',
    'ftp://youtube.com/video',
]);

it('rejects a url it cannot parse into a host', function (string $url): void {
    expect(fn () => SourceUrlGuard::assertAllowed($url))
        ->toThrow(TranscriptFailed::class);
})->with([
    '',
    'not a url',
    '/watch?v=abc',
    'https://',
]);

// `youtube.com.` مضيفٌ صحيح في DNS يُطابق `youtube.com`، ولا يُطابقه نصّاً.
// فلو تُركت النقطة الأخيرة مرّ ما يجب أن يمرّ — والعكس هو الخطر: أن تُقرأ
// لاحقةً مختلفة فتُفتح ثغرة. هنا نُثبت أنّها تُسوّى لا تُلفَّق.
it('normalizes a trailing dot and letter case in the host', function (): void {
    SourceUrlGuard::assertAllowed('https://WWW.YouTube.Com./watch?v=abc');

    expect(true)->toBeTrue();
});

it('carries the host_not_allowed code and an Arabic message', function (): void {
    try {
        SourceUrlGuard::assertAllowed('https://vimeo.com/1');
    } catch (TranscriptFailed $failure) {
        // الرمز للسجلّ، والرسالة العربية للمستخدم — ولا يظهر الرمز فيها.
        expect($failure->errorCode)->toBe(TranscriptErrorCode::HostNotAllowed)
            ->and($failure->userMessage())->toContain('منصّة غير مدعومة')
            ->and($failure->userMessage())->not->toContain('host_not_allowed');

        return;
    }

    $this->fail('كان يجب أن يُرفض المضيف.');
});

// المواصفة §5-أ-1 الفحص الثالث.
it('recognises a playlist link', function (): void {
    expect(SourceUrlGuard::isPlaylist('https://www.youtube.com/playlist?list=PL123'))->toBeTrue()
        ->and(SourceUrlGuard::isPlaylist('https://www.youtube.com/watch?list=PL123'))->toBeTrue();
});

// فيديو داخل قائمة مقبول: الفيديو معلوم بـ `v=`. و`--no-playlist` وحده لا
// يكفي في الحالة الأولى — يأخذ الفيديو الأوّل صامتاً فيُلخَّص درسٌ غير المقصود.
it('accepts a video that merely sits inside a playlist', function (): void {
    expect(SourceUrlGuard::isPlaylist('https://www.youtube.com/watch?v=abc&list=PL123'))->toBeFalse()
        ->and(SourceUrlGuard::isPlaylist('https://youtu.be/abc123'))->toBeFalse()
        ->and(SourceUrlGuard::isPlaylist('https://www.youtube.com/watch?v=abc'))->toBeFalse();
});

// قائمة السماح من الإعدادات لا من الشيفرة: توسيعها قرار يُراجَع.
it('reads the allow list from configuration', function (): void {
    withAllowedHosts(['example.test']);

    SourceUrlGuard::assertAllowed('https://videos.example.test/lecture');

    expect(fn () => SourceUrlGuard::assertAllowed('https://www.youtube.com/watch?v=a'))
        ->toThrow(TranscriptFailed::class);
});

// قائمة فارغة تمنع كلّ شيء. الإعداد الناقص يُغلق ولا يفتح.
it('allows nothing when the list is empty', function (): void {
    withAllowedHosts([]);

    expect(fn () => SourceUrlGuard::assertAllowed('https://www.youtube.com/watch?v=a'))
        ->toThrow(TranscriptFailed::class);
});
