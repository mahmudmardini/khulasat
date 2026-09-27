<?php

declare(strict_types=1);

namespace App\Support\Verification;

use App\Enums\MatchStatus;
use App\Services\Verification\QuranVerifier;
use App\Support\Arabic;
use App\Support\Quran\AyahText;

/**
 * Drops sentences that quote an unverified ayah or hadith inside free text — T-160.
 *
 * حارسُ T-73 يفحص كتل `evidence` وحدها. **ونموذجُ الكتابة يرى التفريغ كاملاً**
 * (T-52)، فإن نقل حديثاً داخل فقرةٍ عادية مرّ بلا تحقّق. وهذا يسدّ الفجوة
 * **بلا نموذج** (CLAUDE.md §2 القاعدة الثالثة).
 *
 * **ما يُعدّ اقتباساً**، في الجملة الواحدة:
 *
 *   ١. ما بين `﴿ ﴾` — فالقوسان دعوى أنّه قرآن.
 *   ٢. ما بين `« »` في جملةٍ **منسوبةٍ** إلى الله أو إلى النبيّ ﷺ.
 *   ٣. ما بعد النقطتين بعد فعل قولٍ قائلُه الله أو النبيّ ﷺ: «قال ﷺ: …».
 *
 * **والكلام المنقول بالمعنى لا يُمسّ** — «بيّن النبيّ ﷺ أنّ الرحمة…» تلخيصٌ
 * لا اقتباس، وهو عمل الملخّص أصلاً.
 *
 * **ويبقى الاقتباس** إن كان بعضَ شاهدٍ مثبَّت غير محذوف، أو طابق المصحف
 * تماماً. **وما سواه تُحذف جملتُه وحدها** وتبقى الفقرة — قرار مالك المنتج،
 * ٢٥ أيلول ٢٠٢٦. فحذفُ الجملة أسلم من تصحيح لفظها، وتصحيحُه نسبةُ لفظٍ لم
 * يُتحقَّق أنّه المقصود.
 */
final class QuoteGuard
{
    /** أقلّ ما يُعدّ اقتباساً بين «» أو بعد النقطتين — دونه اسمٌ أو مصطلح. */
    private const MIN_QUOTE_WORDS = 3;

    /** ألفاظ النسبة إلى الله أو إلى النبيّ ﷺ، وتُطبَّع قبل المقارنة. */
    private const ATTRIBUTIONS = [
        'ﷺ', 'صلى الله عليه وسلم', 'رسول الله', 'النبي', 'قال تعالى', 'قوله تعالى',
        'يقول تعالى', 'قال الله', 'يقول الله', 'عز وجل', 'حديث', 'الحديث', 'رواه', 'أخرجه',
    ];

    /**
     * **والقائلُ قبل النقطتين أضيق**: «قال الشيخ في شرح الحديث: …» كلامُ
     * الشيخ لا حديث. فلا يُعدّ ما بعد النقطتين اقتباساً إلا إن كان القائل
     * الله أو النبيّ ﷺ.
     */
    private const SPEAKERS = ['ﷺ', 'صلى الله عليه وسلم', 'رسول الله', 'النبي', 'تعالى', 'عز وجل'];

    /** @var list<string> */
    private array $settled;

    /** @var list<string> */
    private array $markers;

    /** @var list<string> */
    private array $speakers;

    /** @var list<string> */
    private array $dropped = [];

    /**
     * @param  list<string>  $settledTexts  ألفاظ الشواهد المثبَّتة غير المحذوفة.
     */
    public function __construct(array $settledTexts, private readonly QuranVerifier $quran)
    {
        $this->settled = array_values(array_filter(array_map(
            static fn (string $text): string => self::key($text),
            $settledTexts,
        )));

        $this->markers = self::padded(self::ATTRIBUTIONS);
        $this->speakers = self::padded(self::SPEAKERS);
    }

    /**
     * @param  list<string>  $settledTexts
     */
    public static function for(array $settledTexts): self
    {
        return new self($settledTexts, app(QuranVerifier::class));
    }

    /** النصّ بلا جُمَله ذات الاقتباس غير المحقَّق — ويعود كما هو إن لم يُحذف منه شيء. */
    public function clean(string $text): string
    {
        if (! preg_match('/[﴿«:]/u', $text)) {
            return $text;
        }

        $kept = '';
        $changed = false;

        foreach (self::sentences($text) as [$sentence, $separator]) {
            if ($this->quotesUnverified($sentence)) {
                $this->dropped[] = trim($sentence);
                $changed = true;

                continue;
            }

            $kept .= $sentence.$separator;
        }

        return $changed ? trim($kept) : $text;
    }

    /**
     * الجمل المحذوفة منذ أُنشئ الحارس — للتقييد لا للعرض.
     *
     * @return list<string>
     */
    public function dropped(): array
    {
        return $this->dropped;
    }

    private function quotesUnverified(string $sentence): bool
    {
        foreach ($this->quotes($sentence) as $quote) {
            if (! $this->verified($quote)) {
                return true;
            }
        }

        return false;
    }

    /** @return list<string> */
    private function quotes(string $sentence): array
    {
        $plain = Arabic::stripDiacritics($sentence);
        $quotes = [];

        preg_match_all('/﴿([^﴾]*)﴾/u', $plain, $ayat);

        foreach ($ayat[1] as $ayah) {
            $quotes[] = $ayah;
        }

        if (! $this->attributed($plain)) {
            return $quotes;
        }

        preg_match_all('/«([^»]*)»/u', $plain, $cited);

        foreach ($cited[1] as $quote) {
            if (self::words($quote) >= self::MIN_QUOTE_WORDS) {
                $quotes[] = $quote;
            }
        }

        // «قال ﷺ: …» بلا أقواس — ما بعد النقطتين هو المنقول.
        if ($ayat[1] === [] && $cited[1] === []
            && preg_match('/((?:^|\s)[وف]?(?:قال|قالت|يقول|تقول|قوله)(?=[\s:])[^:«»﴿]{0,80}):\s*(.+)$/u', $plain, $said)
            && $this->mentions($said[1], $this->speakers)
            && self::words($said[2]) >= self::MIN_QUOTE_WORDS
        ) {
            $quotes[] = $said[2];
        }

        return $quotes;
    }

    private function attributed(string $plain): bool
    {
        return $this->mentions($plain, $this->markers);
    }

    /** @param  list<string>  $words  مطبَّعةً ومحاطةً بمسافتين. */
    private function mentions(string $plain, array $words): bool
    {
        $normalized = ' '.Arabic::normalize($plain).' ';

        foreach ($words as $word) {
            if (str_contains($normalized, $word)) {
                return true;
            }
        }

        // ﷺ قد تلتصق بما قبلها («النبيﷺ»)، فلا تحيط بها مسافتان.
        return str_contains($plain, 'ﷺ');
    }

    /**
     * @param  list<string>  $words
     * @return list<string>
     */
    private static function padded(array $words): array
    {
        return array_map(static fn (string $word): string => ' '.Arabic::normalize($word).' ', $words);
    }

    private function verified(string $quote): bool
    {
        $needle = self::key($quote);

        if ($needle === '') {
            return true;
        }

        /*
         * **بعضُ شاهدٍ مثبَّت لا أكثر منه.** فاقتباسٌ يحتوي الشاهد ويزيد
         * عليه يحمل كلماتٍ لم يُتحقَّق منها، وهي بعينها ما يُحرس.
         */
        foreach ($this->settled as $settled) {
            if (str_contains($settled, $needle)) {
                return true;
            }
        }

        // والآية الموافقة للمصحف حرفاً صحيحةُ اللفظ وإن لم تُستخرج شاهداً.
        return $this->quran->verify(new EvidenceInput('ayah', $needle))->status === MatchStatus::Exact;
    }

    /**
     * الجمل بفواصلها — **ولا يُقطع داخل الأقواس**: نقطةٌ داخل «…» ليست آخر جملة.
     *
     * @return list<array{string, string}>
     */
    private static function sentences(string $text): array
    {
        $sentences = [];
        $current = '';
        $depth = 0;
        $chars = mb_str_split($text);
        $count = count($chars);

        for ($i = 0; $i < $count; $i++) {
            $char = $chars[$i];

            if ($char === '«' || $char === '﴿') {
                $depth++;
            } elseif (($char === '»' || $char === '﴾') && $depth > 0) {
                $depth--;
            }

            if ($depth === 0 && $char === "\n") {
                $sentences[] = [$current, "\n"];
                $current = '';

                continue;
            }

            $current .= $char;

            if ($depth === 0 && preg_match('/[.!?؟؛]/u', $char) && isset($chars[$i + 1]) && preg_match('/\s/u', $chars[$i + 1])) {
                $separator = '';

                while (isset($chars[$i + 1]) && $chars[$i + 1] !== "\n" && preg_match('/\s/u', $chars[$i + 1])) {
                    $separator .= $chars[++$i];
                }

                $sentences[] = [$current, $separator];
                $current = '';
            }
        }

        if ($current !== '') {
            $sentences[] = [$current, ''];
        }

        return $sentences;
    }

    /** مفتاحُ المقارنة: بلا زينة الآية ولا أرقامها، ثمّ التطبيع الواحد. */
    private static function key(string $text): string
    {
        $text = (string) preg_replace('/[0-9٠-٩۝]/u', ' ', AyahText::plain($text));

        return Arabic::normalize($text);
    }

    private static function words(string $text): int
    {
        $normalized = Arabic::normalize($text);

        return $normalized === '' ? 0 : count(explode(' ', $normalized));
    }
}
