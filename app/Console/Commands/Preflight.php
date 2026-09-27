<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Enums\Locale;
use App\Enums\Stage;
use App\Models\Hadith;
use App\Models\ModelConfig;
use App\Models\QuranAyah;
use App\Models\QuranTranslation;
use App\Support\Model\StagePrompt;
use App\Support\Quran\QuranSync;
use App\Support\Verification\DomainPolicy;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;
use Throwable;

/**
 * Is this deployment ready to spend real money? — T-35.
 *
 * تبديلُ `MODEL_GATEWAY=fake` إلى `real` يفتح الإنفاق، **والنقصُ لا يظهر
 * إلّا في أوّل مهمّة عميل**: مرحلةٌ بلا صفٍّ في `model_config`، أو مفتاحُ
 * مزوّدٍ بديلٍ فارغ، أو مصحفٌ غير مبذور. وكلّها تُكتشف بعد أن تُصرف
 * توكنز وتقف مهمّة.
 *
 * **ولا نداء نموذج هنا البتّة.** يقرأ الإعداد والجدول والملفّات، ويقول
 * ما ينقص. ولو نادى نموذجاً واحداً «ليتأكّد» لصار الفحصُ نفسه إنفاقاً،
 * ولاحتاج إذناً — CLAUDE.md §0.
 *
 * **ولا يطبع قيمة مفتاح** — §12: يقول «مضبوط» أو «فارغ» ولا شيء بينهما.
 *
 * @see TASKS.md T-35
 */
class Preflight extends Command
{
    protected $signature = 'khulasah:preflight
                            {--domain= : مجالٌ يُفحص بدل الشرعي}';

    protected $description = 'فحص الجاهزية قبل التشغيل الحقيقي — بلا نداء نموذج';

    /** @var list<array{level: string, area: string, message: string}> */
    private array $findings = [];

    public function handle(): int
    {
        $domain = (string) ($this->option('domain') ?: DomainPolicy::DEFAULT);

        $this->line('');
        $this->line('  فحص الجاهزية — لا نداء نموذج، ولا إنفاق.');
        $this->line('  '.str_repeat('─', 62));

        $real = $this->checkGateway();
        $this->checkKeys($real);
        $this->checkStages($domain, $real);
        $this->checkSpendCaps();
        $this->checkCorpora();
        $this->checkQuranSource();
        $this->checkTranscript();
        $this->checkPublishing();

        return $this->render($real);
    }

    /** أفي الوضع الحقيقي نحن أم الوهمي؟ */
    private function checkGateway(): bool
    {
        $gateway = strtolower(trim((string) config('khulasah.model.gateway')));
        $real = $gateway === 'real';

        if ($real) {
            $this->pass('البوّابة', 'حقيقية — النداءات تُدفع من الحساب.');
        } else {
            // **ليس هذا خطأً**: الوهمية هي الافتراض الصحيح في التطوير.
            // ويُقال صراحةً لأنّ من يشغّل الفحص يريد أن يعرف أين هو.
            $this->note('البوّابة', "وهمية (`MODEL_GATEWAY={$gateway}`) — لا نداء حقيقي.");
        }

        return $real;
    }

    /**
     * المفاتيح **موجودةً لا صحيحةً**: صحّتُها لا تُعرف إلّا بنداء، والنداء إنفاق.
     */
    private function checkKeys(bool $real): void
    {
        $providers = [
            'anthropic' => 'ANTHROPIC_API_KEY',
            'openai' => 'OPENAI_API_KEY',
            'google' => 'GOOGLE_AI_API_KEY',
        ];

        foreach ($providers as $provider => $variable) {
            $set = trim((string) config("khulasah.model.{$provider}.api_key")) !== '';

            if ($set) {
                $this->pass('المفاتيح', "{$variable} مضبوط.");

                continue;
            }

            // مزوّدٌ بلا مفتاح لا يُنادى **ولا يصلح بديلاً** — §6-أ. فغيابُه
            // في الوضع الحقيقي حاجبٌ لا تنبيه.
            $real
                ? $this->blocked('المفاتيح', "{$variable} فارغ — ومزوّدٌ بلا مفتاح لا يصلح بديلاً.")
                : $this->note('المفاتيح', "{$variable} فارغ.");
        }
    }

    /** لكلّ مرحلة تُنادى: صفٌّ فعّال، وسعرٌ، وملفُّ تعليمات. */
    private function checkStages(string $domain, bool $real): void
    {
        if (! Schema::hasTable('model_config')) {
            $this->blocked('الجدول', 'جدول `model_config` غير مهاجَر. شغّل `php artisan migrate`.');

            return;
        }

        foreach (Stage::cases() as $stage) {
            $path = StagePrompt::path($stage, $domain);

            if (! is_file($path)) {
                $this->blocked('التعليمات', "لا ملفّ تعليمات للمرحلة `{$stage->value}` على {$path}.");
            }

            $config = ModelConfig::forStage($stage);

            if ($config === null) {
                // بلا صفٍّ تقف المرحلة بـ`model_not_configured` عند أوّل نداء.
                $this->blocked('النماذج', "المرحلة `{$stage->value}` بلا صفٍّ فعّال في `model_config`.");

                continue;
            }

            $this->checkStageRow($stage, $config, $real);
        }
    }

    private function checkStageRow(Stage $stage, ModelConfig $config, bool $real): void
    {
        $model = (string) $config->model_id;
        $provider = (string) $config->provider;

        // **سعرُ الصفر يُفسد سقف الإنفاق صامتاً**: يُنفَق ولا يُسجَّل شيء،
        // فلا يُوقف الطابورَ حدٌّ ولا يُنبّه تنبيه — §11.
        if ((float) $config->input_price_per_m <= 0.0 || (float) $config->output_price_per_m <= 0.0) {
            $this->blocked('الأسعار', "المرحلة `{$stage->value}` بسعرٍ صفريّ — الكلفة لن تُسجَّل وسقفُ الإنفاق لن يحرس.");
        }

        if (! in_array($provider, ['anthropic', 'openai', 'google'], true)) {
            $this->blocked('النماذج', "المرحلة `{$stage->value}` تشير إلى مزوّد بلا محوّل: `{$provider}`.");
        }

        $fallbackProvider = (string) ($config->fallback_provider ?? '');

        if ($fallbackProvider === '') {
            $this->note('البدائل', "المرحلة `{$stage->value}` بلا بديل. وسقوطُ المزوّد يوقفها.");
        } elseif ($fallbackProvider === $provider) {
            // §6-أ: «البديل من مزوّد مختلف لا من العائلة نفسها».
            $this->blocked('البدائل', "بديلُ `{$stage->value}` من المزوّد نفسه (`{$provider}`) — وانقطاعُه يصيب نماذجه كلّها.");
        } elseif ($real && trim((string) config("khulasah.model.{$fallbackProvider}.api_key")) === '') {
            $this->blocked('البدائل', "بديلُ `{$stage->value}` عند `{$fallbackProvider}` ولا مفتاح له.");
        }

        $this->pass('النماذج', sprintf('%-20s %s · %s', $stage->value, $provider, $model));
    }

    private function checkSpendCaps(): void
    {
        $daily = (float) config('khulasah.spend.daily_usd');
        $monthly = (float) config('khulasah.spend.monthly_usd');

        if ($daily <= 0.0 || $monthly <= 0.0) {
            $this->blocked('الإنفاق', 'سقفُ الإنفاق صفرٌ أو غير مضبوط — ولا شيء يوقف الطابور.');

            return;
        }

        $this->pass('الإنفاق', "سقفٌ يوميّ \${$daily} · شهريّ \${$monthly}.");

        // **وسقفُ مستوى المزوّد قد يكون دون سقفنا**: من كان في مستوى البداية
        // عند أنثروبيك فسقفُه الشهريّ 500$، فيقف قبل سقفنا ولا يبلغه.
        if ($monthly > 500.0) {
            $this->note('الإنفاق', 'سقفُك الشهريّ فوق 500$ — تأكّد أنّ مستوى حسابك عند المزوّد يبلغه.');
        }
    }

    /** المصحف والمدوّنة: بلا بذرٍ يسقط التحقّق كلّه إلى `needs_review`. */
    private function checkCorpora(): void
    {
        foreach ([
            ['المصحف', QuranAyah::class, 'quran_ayat', 'php artisan khulasah:seed-quran', 6_000],
            ['الحديث', Hadith::class, 'hadith_corpus', 'php artisan khulasah:seed-hadith', 1],
        ] as [$label, $model, $table, $command, $minimum]) {
            if (! Schema::hasTable($table)) {
                $this->blocked($label, "جدول `{$table}` غير مهاجَر.");

                continue;
            }

            try {
                $count = $model::query()->count();
            } catch (Throwable $exception) {
                $this->blocked($label, 'تعذّر عدُّ الصفوف: '.$exception->getMessage());

                continue;
            }

            $count >= $minimum
                ? $this->pass($label, number_format($count).' صفّاً مبذوراً.')
                : $this->blocked($label, "غير مبذور ({$count} صفّاً). شغّل `{$command}`.");
        }
    }

    /**
     * ترجمات الآيات، ومزامنةُ المصدر — T-161.
     *
     * ★ **الترجمةُ الناقصة حاجب.** جدولٌ غير مبذور يُخرج آياتٍ بلا ترجمةٍ في
     * صفحاتٍ منشورة، ولم يُعلَم بذلك على الخادم في ١٦ أيلول إلّا ببلاغ.
     *
     * **والمزامنةُ الفائتة تنبيهٌ لا حاجب**: المصحف يعمل كما هو، لكنّ شروط
     * Quran Foundation لا تُجيز حفظه أكثر من أسبوعٍ بلا مزامنة.
     */
    private function checkQuranSource(): void
    {
        if (! Schema::hasTable('quran_translations')) {
            $this->blocked('ترجمات الآيات', 'جدول `quran_translations` غير مهاجَر.');

            return;
        }

        foreach (Locale::translatable() as $locale) {
            $count = QuranTranslation::query()->where('locale', $locale->value)->count();

            $count === 6_236
                ? $this->pass('ترجمات الآيات', "{$locale->label()}: {$locale->quranTranslationName()} كاملة.")
                : $this->blocked('ترجمات الآيات', "{$locale->label()} ناقصة ({$count} من ٦٢٣٦). شغّل `php artisan khulasah:sync-quran`.");
        }

        $keys = trim((string) config('khulasah.quran.client_id')) !== ''
            && trim((string) config('khulasah.quran.client_secret')) !== '';

        if (! $keys) {
            $this->note('مصدر المصحف', 'QURAN_CLIENT_ID أو QURAN_CLIENT_SECRET فارغ — لا مزامنة ممكنة مع Quran Foundation.');
        }

        $stale = QuranSync::stale();

        $stale === []
            ? $this->pass('مصدر المصحف', 'زُومن مع Quran Foundation في الأيام السبعة الأخيرة.')
            : $this->note('مصدر المصحف', 'لم يُزامَن في الأيام السبعة الأخيرة: '.implode(' · ', $stale)
                .'. شروط Quran Foundation لا تُجيز حفظ محتواها أكثر من أسبوع بلا مزامنة — شغّل `php artisan khulasah:sync-quran`.');
    }

    private function checkTranscript(): void
    {
        $binary = (string) config('khulasah.transcript.ytdlp_bin');

        is_file($binary) && is_executable($binary)
            ? $this->pass('التفريغ', "yt-dlp حاضر على {$binary}.")
            // ولا يمنع هذا التشغيل: المسار اليدوي يبقى متاحاً دائماً — §5-أ-5.
            : $this->note('التفريغ', "yt-dlp غير موجود على {$binary} — يبقى اللصق اليدوي ورفعُ الملفّ.");

        $whisper = trim((string) config('khulasah.transcript.whisper.api_key', ''));

        $whisper !== ''
            ? $this->pass('التفريغ', 'مفتاح التفريغ الصوتي مضبوط.')
            : $this->note('التفريغ', 'WHISPER_API_KEY فارغ — لا تفريغ صوتيّ، والنصّ يُلصق يدوياً.');
    }

    private function checkPublishing(): void
    {
        $disk = (string) config('khulasah.publish.disk');
        $cdn = trim((string) config('khulasah.publish.cdn_url'));

        $this->pass('النشر', "القرص `{$disk}`".($cdn === '' ? '' : " · CDN {$cdn}"));

        if ($cdn === '') {
            $this->note('النشر', 'لا CDN — تُخدَم الملفّات من رابط القرص نفسه.');
        }
    }

    // ── العرض ────────────────────────────────────────────────────────

    private function pass(string $area, string $message): void
    {
        $this->findings[] = ['level' => 'pass', 'area' => $area, 'message' => $message];
    }

    private function note(string $area, string $message): void
    {
        $this->findings[] = ['level' => 'note', 'area' => $area, 'message' => $message];
    }

    private function blocked(string $area, string $message): void
    {
        $this->findings[] = ['level' => 'fail', 'area' => $area, 'message' => $message];
    }

    private function render(bool $real): int
    {
        foreach ($this->findings as $finding) {
            $mark = match ($finding['level']) {
                'pass' => '<fg=green>✓</>',
                'note' => '<fg=yellow>•</>',
                default => '<fg=red>✗</>',
            };

            $this->line(sprintf('  %s  %-10s %s', $mark, $finding['area'], $finding['message']));
        }

        $failures = array_filter($this->findings, static fn (array $f): bool => $f['level'] === 'fail');
        $notes = array_filter($this->findings, static fn (array $f): bool => $f['level'] === 'note');

        $this->line('  '.str_repeat('─', 62));

        if ($failures !== []) {
            $this->line('');
            $this->error(sprintf('  %d حاجباً · %d تنبيهاً. لا تفتح الوضع الحقيقي قبل رفعها.', count($failures), count($notes)));
            $this->line('');

            return self::FAILURE;
        }

        $this->line('');

        $real
            ? $this->info(sprintf('  جاهز. %d تنبيهاً لا يمنع.', count($notes)))
            : $this->info(sprintf('  جاهز للتبديل إلى `MODEL_GATEWAY=real`. %d تنبيهاً لا يمنع.', count($notes)));

        $this->line('');

        return self::SUCCESS;
    }
}
