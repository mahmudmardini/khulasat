<?php

declare(strict_types=1);

use App\Services\Transcript\YtDlp;
use Illuminate\Http\Request;
use Illuminate\Process\PendingProcess;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Route;

/*
 * تجهيزُ النشر — T-124.
 *
 * ثلاثةٌ لا يكشفها اختبارُ ميزةٍ واحدة، وكلُّها لا تُرى إلّا على خادمٍ
 * حقيقيّ خلف وسيط: **كعكةٌ بلا `Secure`**، و**مضيفٌ ملفَّق يُوثَق**،
 * و**مهلةٌ طويلة في دورة الطلب**. فتُقاس هنا قبل النشر لا بعده.
 */

// ── الوسيط الأماميّ — بروتوكولُه يُوثَق، ومضيفُه لا ───────────────

function proxyProbe(): void
{
    // مسارُ فحصٍ في الاختبار وحده: الحرّاسُ العامّون يجرون قبله كما يجرون
    // قبل كلّ مسار، فيُقرأ أثرُهم في الطلب نفسه.
    Route::get('/__t124_probe', fn (Request $request): array => [
        'secure' => $request->isSecure(),
        'host' => $request->getHost(),
        'root' => $request->getSchemeAndHttpHost(),
    ]);
}

it('reads https from the proxy so the secure cookie can be set', function (): void {
    proxyProbe();

    // بلا هذا يرى Laravel الطلبَ `http` خلف nginx، فلا يضع `Secure` على
    // الكعكة وإن طُلب — والكعكةُ الآمنة على اتّصالٍ يُرى غيرَ آمن لا تُرسَل.
    $this->get('/__t124_probe', ['X-Forwarded-Proto' => 'https'])
        ->assertOk()
        ->assertJson(['secure' => true]);
});

it('still reads plain http when the proxy says so', function (): void {
    proxyProbe();

    $this->get('/__t124_probe')
        ->assertOk()
        ->assertJson(['secure' => false]);
});

/*
 * ★ **والمضيفُ المُمرَّر لا يُوثَق** — وهو في افتراض Laravel موثوق.
 *
 * nginx يمرّر ما لم يعرفه كما جاء، فترويسةٌ ملفَّقة تجعل `url()` تبني على
 * مضيف المهاجم: **رابطُ استعادة كلمة السرّ يصل صاحبَه بنطاقٍ ليس نطاقنا**.
 */
it('never trusts a forwarded host header', function (): void {
    proxyProbe();

    $this->get('/__t124_probe', ['X-Forwarded-Host' => 'evil.test'])
        ->assertOk()
        ->assertJsonMissing(['host' => 'evil.test'])
        ->assertJson(['host' => 'localhost']);
});

// ── كعكةُ الجلسة — آمنةٌ في الإنتاج بلا سطرٍ يُتذكَّر ──────────────

/**
 * يقرأ `config/session.php` من جديد ببيئةٍ مفروضة.
 *
 * **والقيمُ تُكتب في `$_SERVER` و`$_ENV` رأساً** لا بـ`Env::getRepository()`:
 * مستودعُ البيئة لا يُبدّل متغيّراً مضبوطاً أصلاً (وphpunit.xml يضبط
 * `APP_ENV` بـ`force`)، وقارئُ `env()` يقرأ من هذين.
 */
function sessionConfigFor(string $environment, ?string $override = null): array
{
    $previous = ['APP_ENV' => $_SERVER['APP_ENV'] ?? null, 'SESSION_SECURE_COOKIE' => $_SERVER['SESSION_SECURE_COOKIE'] ?? null];

    $apply = function (string $key, ?string $value): void {
        if ($value === null) {
            unset($_SERVER[$key], $_ENV[$key]);

            return;
        }

        $_SERVER[$key] = $_ENV[$key] = $value;
    };

    $apply('APP_ENV', $environment);
    $apply('SESSION_SECURE_COOKIE', $override);

    try {
        return require base_path('config/session.php');
    } finally {
        foreach ($previous as $key => $value) {
            $apply($key, $value);
        }
    }
}

it('marks the session cookie secure in production without asking', function (): void {
    expect(sessionConfigFor('production')['secure'])->toBeTrue();
});

// ودونها محليّاً: كعكةٌ آمنةٌ على `http://localhost` لا تُحفظ، فينكسر الدخول.
it('leaves the session cookie open outside production', function (): void {
    expect(sessionConfigFor('local')['secure'])->toBeFalse()
        ->and(sessionConfigFor('testing')['secure'])->toBeFalse();
});

it('lets the environment override the derived default', function (): void {
    expect(sessionConfigFor('production', 'false')['secure'])->toBeFalse()
        ->and(sessionConfigFor('local', 'true')['secure'])->toBeTrue();
});

// ── مهلةُ الفحص السريع — أقصرُ من مهلة التنزيل ────────────────────

/** مهلةُ أوّل عمليةٍ شُغّلت. */
function ranTimeout(): ?float
{
    $captured = null;

    Process::assertRan(function (PendingProcess $process) use (&$captured): bool {
        $captured = $process->timeout;

        return true;
    });

    return $captured;
}

/*
 * `preflight` وحدَها تجري **في دورة الطلب** لا في الطابور. وبالمهلة
 * الطويلة يحبس مضيفٌ بطيءٌ عاملَ PHP-FPM خمس دقائق على «نظرةٍ رخيصة»
 * يُنتظر جوابُها في ثوانٍ.
 */
it('gives the request-cycle preflight a shorter leash than the download', function (): void {
    Process::preventStrayProcesses();
    Process::fake(['*' => Process::result(output: (string) json_encode(['title' => 'درس', 'duration' => 600]))]);

    app(YtDlp::class)->preflight('https://www.youtube.com/watch?v=abc');

    expect(ranTimeout())
        ->toBe((float) config('khulasah.transcript.ytdlp_preflight_timeout'))
        ->toBeLessThan((float) config('khulasah.transcript.ytdlp_timeout'));
});

it('keeps the long leash for the queued download', function (): void {
    Process::preventStrayProcesses();
    Process::fake(['*' => Process::result(output: '')]);

    try {
        app(YtDlp::class)->extractAudio('https://www.youtube.com/watch?v=abc', sys_get_temp_dir());
    } catch (Throwable) {
        // لا ملفَّ صوتٍ يُكتب في التزييف — والمقصود المهلةُ وحدها.
    }

    expect(ranTimeout())->toBe((float) config('khulasah.transcript.ytdlp_timeout'));
});
