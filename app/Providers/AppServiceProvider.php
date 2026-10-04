<?php

declare(strict_types=1);

namespace App\Providers;

use App\Actions\Auth\ResolveLandingDestination;
use App\Contracts\HadithProvider;
use App\Contracts\ModelDriver;
use App\Contracts\ModelGateway;
use App\Contracts\PublishStore;
use App\Contracts\QuranContent;
use App\Contracts\ShareCardCapturer;
use App\Contracts\SpeechToText;
use App\Contracts\VerifierRegistry;
use App\Services\Model\DatabaseModelGateway;
use App\Services\Model\Drivers\AnthropicDriver;
use App\Services\Model\Drivers\GoogleDriver;
use App\Services\Model\Drivers\OpenAiDriver;
use App\Services\Model\FakeModelGateway;
use App\Services\Model\ModelCallRecorder;
use App\Services\Quran\QuranFoundationContent;
use App\Services\ShareCard\ChromeShareCardCapturer;
use App\Services\ShareCard\NullShareCardCapturer;
use App\Services\Transcript\ManualUpload;
use App\Services\Transcript\Speech\FakeSpeechToText;
use App\Services\Transcript\Speech\GeminiSpeech;
use App\Services\Transcript\Speech\WhisperApi;
use App\Services\Transcript\TranscriptResolver;
use App\Services\Transcript\WhisperAudio;
use App\Services\Transcript\YoutubeCaptions;
use App\Services\Verification\ConfiguredVerifierRegistry;
use App\Services\Verification\HadithVerifier;
use App\Support\TenantContext;
use Illuminate\Auth\Middleware\RedirectIfAuthenticated;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // تخزين النشر خلف عقده — §9. والمزوّد يتبدّل (R2 · Spaces)،
        // والاختبارات لا ترفع إلى شبكة.
        $this->app->bind(
            PublishStore::class,
            \App\Services\Publish\PublishStore::class,
        );

        // مصدر المصحف وترجماته خلف عقده — T-161. والاختبارات لا تنادي الشبكة.
        $this->app->bind(
            QuranContent::class,
            QuranFoundationContent::class,
        );

        // ملتقِطُ بطاقة المشاركة — T-144. مُطفأٌ افتراضاً، فلا متصفّحَ يُطلب
        // في التطوير ولا في CI، ولا تسقط خلاصةٌ لغيابه.
        $this->app->bind(ShareCardCapturer::class, function (): ShareCardCapturer {
            $config = (array) config('khulasah.share_card');

            return ($config['capturer'] ?? 'none') === 'chrome'
                ? new ChromeShareCardCapturer(
                    binary: (string) $config['chrome_binary'],
                    timeout: (int) $config['timeout'],
                    noSandbox: (bool) $config['no_sandbox'],
                )
                : new NullShareCardCapturer;
        });

        // مفرد لكل طلب أو مهمّة — الحاجز كلّه يقرأ منه.
        $this->app->singleton(TenantContext::class);

        $this->registerSpeechToText();
        $this->registerTranscriptSources();
        $this->registerModelGateway();
        $this->registerVerifiers();
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->redirectAuthenticatedGuests();
    }

    /**
     * صرفُ مَن معه جلسة عن شاشات الزائر — T-129.
     *
     * `RedirectIfAuthenticated` افتراضُه `route('home')`، وهو هنا صفحةُ
     * التعريف العامّة (T-113). **فالمشرف الداخلُ يضغط «دخول» فيُصرف إلى
     * الصفحة التي جاء منها** — لأنّ الجذر يفحص الحارس الافتراضي وحده ولا
     * يرى حارس `admin`. دورةٌ بلا مخرج.
     *
     * وكلٌّ يُصرف إلى لوحته هو: المشرفُ إلى `/admin`، ومستخدمُ الجهة إلى
     * فهرسه.
     */
    private function redirectAuthenticatedGuests(): void
    {
        RedirectIfAuthenticated::redirectUsing(
            fn (): string => app(ResolveLandingDestination::class)->handle() ?? route('home'),
        );
    }

    /**
     * **الوهميّ هو الافتراضي** — CLAUDE.md §2 القاعدة السابعة.
     *
     * ولا يُبدَّل إلا بـ `WHISPER_PROVIDER` مضبوطاً صراحةً. والافتراض معكوساً
     * — أن يكون الحقيقي أصلاً ويُعطَّل في الاختبار — يعني أنّ نسيان الإعداد
     * يُنفق مالاً، ونسيانَه هنا لا يُنفق شيئاً.
     */
    private function registerSpeechToText(): void
    {
        $this->app->bind(SpeechToText::class, function (): SpeechToText {
            $provider = trim((string) config('khulasah.transcript.whisper.provider'));

            return match ($provider) {
                '', 'fake' => new FakeSpeechToText,
                // قرار الفريق، 4 أكتوبر 2026 — {@see GeminiSpeech}.
                'gemini' => $this->app->make(GeminiSpeech::class),
                // وما سواهما خدمةٌ بصيغة Whisper: OpenAI أو Groq أو غيرهما.
                default => $this->app->make(WhisperApi::class),
            };
        });
    }

    /**
     * البوّابة التي تُنادى بها النماذج — المواصفة §6.
     *
     * والمحوّلات تُسجَّل باسم المزوّد كما يُكتب في `model_config.provider`،
     * **من عائلتين مختلفتين**: البديل من مزوّد مختلف لا من العائلة نفسها،
     * إذ انقطاع المزوّد يصيب نماذجه كلّها (§6-أ).
     *
     * وتبديلُها إلى الوهمية عمل T-10ج، وهي التي تصير الافتراضية في
     * `local` و`testing` — CLAUDE.md §2 القاعدة السابعة.
     */
    private function registerModelGateway(): void
    {
        $this->app->singleton(ModelGateway::class, function (): ModelGateway {
            // **الوهمية هي الافتراضية** في التطوير والاختبار — CLAUDE.md §2
            // القاعدة السابعة، ولا تُبدَّل إلا بـ MODEL_GATEWAY=real صريحاً.
            // والافتراض معكوساً — أن يكون الحقيقي أصلاً ويُعطَّل في الاختبار —
            // يعني أنّ نسيان الإعداد يُنفق مالاً، ونسيانَه هنا لا يُنفق شيئاً.
            return $this->usesRealGateway()
                ? $this->app->make(DatabaseModelGateway::class)
                : $this->app->make(FakeModelGateway::class);
        });

        $this->app->singleton(DatabaseModelGateway::class, fn (): DatabaseModelGateway => new DatabaseModelGateway(
            drivers: collect([
                $this->app->make(AnthropicDriver::class),
                $this->app->make(OpenAiDriver::class),
                $this->app->make(GoogleDriver::class),
            ])->keyBy(fn (ModelDriver $driver): string => $driver->name())->all(),
            recorder: $this->app->make(ModelCallRecorder::class),
        ));

        $this->app->singleton(FakeModelGateway::class);

        // **واحدٌ للبوّابة ولمن يستدعيها** — T-181: أداةُ التحقّق تُعلن عليه
        // طلبَها، والبوّابةُ تقيّد عليه الكلفة. فنسختان منه تُضيّعان الإعلان.
        $this->app->singleton(ModelCallRecorder::class);
    }

    /**
     * أتُستعمل البوّابة الحقيقية؟
     *
     * `MODEL_GATEWAY=real` وحدها تفتحها. وأيّ قيمة أخرى — أو غيابُها —
     * تُبقي الوهمية، **فالإعداد الناقص لا يُنفق مالاً**.
     */
    private function usesRealGateway(): bool
    {
        return strtolower(trim((string) config('khulasah.model.gateway'))) === 'real';
    }

    /**
     * مصادر النصّ بترتيب جدول §5-أ-2 — **والترتيب هنا هو الأولوية**.
     *
     * واليدويّ آخرها دائماً: «يبقى متاحاً دائماً… وهو ما يُنقذ الجهة حين
     * يُخفق كلّ ما سبق» (§5-أ-5). ولو تقدّم لالتُقط كلّ درسٍ عنده نصٌّ ملصوق
     * قبل أن تُجرَّب ترجمةُ المنصّة، وهي أدقّ وأرخص.
     */
    private function registerTranscriptSources(): void
    {
        $this->app->singleton(TranscriptResolver::class, fn (): TranscriptResolver => new TranscriptResolver([
            $this->app->make(YoutubeCaptions::class),
            $this->app->make(WhisperAudio::class),
            $this->app->make(ManualUpload::class),
        ]));
    }

    /**
     * سجلّ المحقّقين ومزوّدو الحديث — المواصفة §7-4 وT-02ب.
     *
     * **والمزوّدون يُحقنون بالسياق لا يُبنون في المحقّق**، فيبقى المحقّق
     * دالّةً خالصة تُختبر بمزوّدٍ تحت سيطرة الاختبار. وترتيبهم من الإعدادات
     * لا من الشيفرة — §7-4 — وهو اليوم مزوّد واحد محلّي (T-05ب).
     */
    private function registerVerifiers(): void
    {
        $this->app->singleton(VerifierRegistry::class, ConfiguredVerifierRegistry::class);

        $this->app->when(HadithVerifier::class)
            ->needs('$providers')
            ->give(fn (): array => array_map(
                fn (string $class): HadithProvider => $this->app->make($class),
                config('khulasah.hadith.providers'),
            ));
    }
}
