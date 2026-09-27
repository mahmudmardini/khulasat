<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Contracts\QuranContent;
use App\Enums\Locale;
use App\Models\QuranTranslation;
use App\Support\Quran\QuranSync;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Seeds approved Qur'an translations — T-38.
 *
 * ★★ **ولا يترجم نموذجٌ آيةً.** الترجمات معتمدةٌ ومنشورة في المصدر الذي
 * نبذر منه النصّ العربيّ أصلاً، فتُبذر كما بُذر — و«نموذجٌ يترجم آيةً وثمّ
 * ترجمةٌ معتمدة منشورة، مخاطرةٌ بلا مقابل» (قرار مالك المنتج، 8 أيلول 2026).
 *
 * وبهذا تصير ترجمةُ الآية **حتميّةً كمطابقتها**: تُقرأ من الجدول لا تُولَّد،
 * فلا تتبدّل بين تشغيلين ولا يمسّها اختيارُ نموذجٍ ولا حرارتُه.
 *
 * ★ **وتُجلب من {@see QuranContent} بحساب مطوّر** — T-161: لقطةُ الترجمة من
 * Content Sync، وتُعاد أسبوعياً بـ`khulasah:sync-quran`. فالشروط لا تُجيز
 * حفظها أكثر من أسبوعٍ بلا مزامنة.
 *
 * ويتبع {@see SeedQuran} في كلّ شيء: نداءٌ واحد للغة، وتحقّقٌ من العدد قبل
 * الكتابة، ومعاملةٌ واحدة — **فنصفُ ترجمةٍ في الجدول يعني آيةً تخرج بلا
 * ترجمةٍ في صفحةٍ منشورة**، وهو أسوأ من غيابها كلِّها.
 */
class SeedQuranTranslations extends Command
{
    protected $signature = 'khulasah:seed-quran-translations
                            {--locale= : لغةٌ بعينها (en|tr|ru)، والافتراض كلُّها}
                            {--force : يعيد البذر ولو كانت اللغة مبذورة}';

    protected $description = 'بذر ترجمات القرآن المعتمدة — en · tr · ru';

    private const EXPECTED_AYAT = 6236;

    private QuranContent $source;

    public function handle(QuranContent $source): int
    {
        $this->source = $source;

        $locales = $this->targets();

        if ($locales === []) {
            $this->error('لغةٌ غير معروفة. المتاح: '.implode(' · ', array_map(
                static fn (Locale $l): string => $l->value,
                Locale::translatable(),
            )));

            return self::FAILURE;
        }

        $failed = false;

        foreach ($locales as $locale) {
            if (! $this->seedLocale($locale)) {
                $failed = true;
            }
        }

        return $failed ? self::FAILURE : self::SUCCESS;
    }

    /** @return list<Locale> */
    private function targets(): array
    {
        $only = $this->option('locale');

        if ($only === null) {
            return Locale::translatable();
        }

        $locale = Locale::tryFrom((string) $only);

        // **العربية ليست هدفاً** — هي لغة المصدر، ونصُّها مبذورٌ بـ`seed-quran`.
        return $locale === null || $locale->isSource() ? [] : [$locale];
    }

    private function seedLocale(Locale $locale): bool
    {
        $translationId = $locale->quranTranslationId();

        if ($translationId === null) {
            return true;
        }

        $existing = QuranTranslation::query()->where('locale', $locale->value)->count();

        if ($existing === self::EXPECTED_AYAT && ! $this->option('force')) {
            $this->info("{$locale->label()} مبذورة بالفعل (".self::EXPECTED_AYAT.' آية).');

            return true;
        }

        $this->info("جلب ترجمة {$locale->label()} — {$locale->quranTranslationName()} (#{$translationId})…");

        $verses = array_map(self::clean(...), $this->source->translation($translationId));

        /*
         * **يُتحقّق من العدد قبل الكتابة.** فمصدرٌ ردّ نصفَ الآيات — لانقطاعٍ
         * أو تبدّلٍ في واجهته — يترك ثغراتٍ لا تظهر إلّا في صفحةٍ منشورة
         * تخلو آيةٌ فيها من ترجمتها. فلا يُكتب شيء، ويُقال لماذا.
         */
        if (count($verses) !== self::EXPECTED_AYAT) {
            $this->error(sprintf(
                'عدد الآيات غير متوقّع في %s: %d والمنتظر %d. لم يُكتب شيء.',
                $locale->value, count($verses), self::EXPECTED_AYAT,
            ));

            return false;
        }

        /*
         * **وكلُّ مفتاحٍ آيةٌ في المصحف المبذور.** فالربطُ بمفتاح الآية لا
         * بموضعها في الردّ، وترجمةٌ على مفتاحٍ لا آية له تُنشر تحت غيرها.
         */
        $unknown = array_diff_key($verses, array_flip($this->verseKeys()));

        if ($unknown !== []) {
            $this->error(sprintf(
                'ترجمة %s فيها %d مفتاحاً لا آية لها في المصحف المبذور. شغّل khulasah:seed-quran أوّلاً. لم يُكتب شيء.',
                $locale->value, count($unknown),
            ));

            return false;
        }

        $this->write($locale, $translationId, $verses);
        QuranSync::record(QuranSync::translation($translationId), count($verses));

        $count = QuranTranslation::query()->where('locale', $locale->value)->count();
        $this->newLine();
        $this->info("تمّ. {$count} آية بترجمة {$locale->label()}.");

        return $count === self::EXPECTED_AYAT;
    }

    /**
     * نصُّ الترجمة بلا حواشيه.
     *
     * ★ **والحاشيةُ تُنزع بما فيها لا وسمُها وحده.** المصدرُ يضع رقمَها داخل
     * `<sup foot_note=…>1</sup>`، و`strip_tags` وحدها أبقت الرقمَ ملتصقاً
     * بالكلمة — «His Messenger1» — في ٦١٦ آيةً إنجليزية (T-80).
     */
    public static function clean(string $html): string
    {
        $html = (string) preg_replace('#<sup\b[^>]*>.*?</sup>#is', '', $html);

        return trim(html_entity_decode(strip_tags($html), ENT_QUOTES, 'UTF-8'));
    }

    /** @param  array<string, string>  $verses  `2:255` ← النصّ. */
    private function write(Locale $locale, int $translationId, array $verses): void
    {
        $bar = $this->output->createProgressBar(self::EXPECTED_AYAT);
        $bar->start();

        // معاملة واحدة: إمّا ترجمةٌ كاملة أو لا شيء — نظير `SeedQuran`.
        DB::transaction(function () use ($locale, $translationId, $verses, $bar): void {
            $now = now();

            foreach (array_chunk($verses, 500, true) as $chunk) {
                $rows = [];

                foreach ($chunk as $key => $text) {
                    [$surah, $ayah] = array_map(intval(...), explode(':', (string) $key));

                    $rows[] = [
                        'surah' => $surah,
                        'ayah' => $ayah,
                        'locale' => $locale->value,
                        'translation_id' => $translationId,
                        'text' => $text,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ];
                }

                QuranTranslation::query()->upsert(
                    $rows,
                    ['surah', 'ayah', 'locale'],
                    ['translation_id', 'text', 'updated_at'],
                );

                $bar->advance(count($rows));
            }
        });

        $bar->finish();
    }

    /**
     * مفاتيح الآيات في المصحف المبذور — `2:255`.
     *
     * **فبذرُ النصّ العربي شرطٌ سابق**: بغيره لا يُعرف أيُّ ترجمةٍ لأيّ آية.
     *
     * @return list<string>
     */
    private function verseKeys(): array
    {
        return DB::table('quran_ayat')
            ->orderBy('surah')->orderBy('ayah')
            ->get(['surah', 'ayah'])
            ->map(static fn (object $r): string => $r->surah.':'.$r->ayah)
            ->all();
    }
}
