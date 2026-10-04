<?php

declare(strict_types=1);

namespace App\Support\Render;

use App\Enums\Locale;
use App\Models\EvidenceItem;
use App\Support\Arabic;
use App\Support\Hadith\MatnOpening;
use App\Support\I18n\PageStrings;
use App\Support\Quran\AyahCitation;
use App\Support\Quran\AyahText;

/**
 * شاهدٌ كما يُعرض — بلفظ مصدره ودرجته وتخريجه.
 *
 * **والدرجة تُعرض مع الضعيف وجوباً** — سياسة البيان §7-5: منشورٌ ضعيفٌ بلا
 * بيان درجته يُسقط البوّابة الحاكمة.
 */
final readonly class RenderedEvidence
{
    public function __construct(
        public string $kind,
        public string $text,
        public ?string $sourceRef = null,
        public ?string $takhrij = null,
        public ?string $grade = null,
        public ?string $narrator = null,
        /** عنوانُ الكتاب ورقمُه — بهما يُبنى التخريج بلسان الصفحة، T-69. */
        public ?string $book = null,
        public ?string $hadithNumber = null,
        /** موضعُ الآية — به يُبنى تخريجُها بلسان الصفحة ورابطُها، T-81. */
        public ?int $surah = null,
        public ?int $ayah = null,
        public ?int $ayahEnd = null,
    ) {}

    /**
     * Build the displayed form of a stored evidence row — **بلفظ مصدره**.
     *
     * واحدٌ لكلّ من يعرض شاهداً: الصفحةُ المنشورة (`ContentObject`) وصفحةُ
     * التعريف (T-143). وكان البناءُ داخل `ContentObject` وحده، فنسخُه في
     * موضعٍ ثانٍ يعني أن يُضاف حقلٌ هنا ويُنسى هناك.
     */
    public static function fromItem(EvidenceItem $item): self
    {
        return new self(
            kind: $item->kind,
            text: $item->matched_text ?? $item->raw_text,
            sourceRef: $item->source_ref,
            takhrij: $item->meta('takhrij'),
            grade: $item->meta('grade'),
            narrator: $item->meta('narrator'),
            // بهما يُبنى التخريج بلسان الصفحة — T-69.
            book: $item->meta('book'),
            hadithNumber: $item->meta('hadith_number'),
            // موضعُ الآية — به تخريجُها بلسان الصفحة ورابطُها، T-81.
            surah: self::number($item->meta('surah_number')),
            ayah: self::number($item->meta('ayah_number')),
            ayahEnd: self::number($item->meta('ayah_number_end')),
        );
    }

    /**
     * Collapse repeated citations for the sources list, keeping first-seen order.
     *
     * ★ **الشاهدُ المكرَّر سطرٌ واحد في قائمة التخريج** — T-117، قرار مالك
     * المنتج. محاضرةٌ استشهدت بالحديث في موضعين تُبقيه في المتن موضعين،
     * والقائمةُ حاشيةٌ يُعرف منها الموضع، فتكرارُه فيها لا يفيد شيئاً.
     *
     * **والتمييزُ بالموضع لا باللفظ:** الكتابُ والرقم للحديث، والسورةُ والآيات
     * للآية. فروايتان برقمين شاهدان ولو تقارب لفظهما، ولفظان برقمٍ واحد شاهدٌ
     * تكرّر. وما لا موضعَ له يبقى بعدده، فلا يُدمج شاهدان لا يُعرف أنّهما واحد.
     *
     * @param  list<self>  $items
     * @return list<self>
     */
    public static function distinct(array $items): array
    {
        $seen = [];
        $distinct = [];

        foreach ($items as $item) {
            $key = $item->locationKey();

            if ($key !== null) {
                if (isset($seen[$key])) {
                    continue;
                }

                $seen[$key] = true;
            }

            $distinct[] = $item;
        }

        return $distinct;
    }

    private function locationKey(): ?string
    {
        if ($this->kind === 'ayah' && $this->surah !== null && $this->ayah !== null) {
            return 'ayah:'.$this->surah.':'.$this->ayah.':'.($this->ayahEnd ?? $this->ayah);
        }

        if ($this->book !== null && $this->book !== '' && $this->hadithNumber !== null && $this->hadithNumber !== '') {
            return $this->kind.':'.$this->book.':'.$this->hadithNumber;
        }

        return null;
    }

    private static function number(mixed $value): ?int
    {
        return is_numeric($value) ? (int) $value : null;
    }

    /**
     * سطر التخريج كما يظهر في «مواضع الآيات والأحاديث» — **بلسان الصفحة**.
     *
     * ★ **وكان عربياً في كلّ اللغات**، فيُقرأ الشاهدُ الواحد بتخريجين
     * مختلفين في صفحةٍ واحدة: إنجليزيٍّ في المتن وعربيٍّ في القائمة — T-69.
     *
     * **والتخريجُ يُبنى ولا يُترجَم**: «رواه البخاري، رقم ٩٢٣» اصطلاحٌ يثبت،
     * ونموذجٌ يترجمه في كلّ ملخّصٍ يصرف مالاً ويُخرج صياغةً مختلفة كلَّ مرّة.
     *
     * ويسقط إلى المحفوظ عربياً متى لم يُعرف كتابُه — نصٌّ قائم خيرٌ من فراغ.
     */
    public function citation(Locale $locale = Locale::Ar): string
    {
        return implode(' — ', array_filter([
            $this->attribution($locale),
            PageStrings::grade($this->grade, $locale),
        ]));
    }

    private function attribution(Locale $locale): ?string
    {
        /*
         * ★ **الآيةُ بلسان الصفحة من رقميها** — T-81. وكانت تسقط إلى
         * `source_ref` العربيّ، فيقع «سورة ص، الآية ٢٩» في سطرٍ لاتينيّ
         * وتضطرب الأسطر. **وهو هو تخريجُها تحتها في المتن** (T-80).
         */
        if ($this->kind === 'ayah' && $this->surah !== null && $this->ayah !== null && ! $locale->isSource()) {
            $citation = AyahCitation::for($this->surah, $this->ayah, $this->ayahEnd, $locale);

            if ($citation !== null) {
                return $citation;
            }
        }

        $narrator = PageStrings::narrator($this->book, $locale);

        if ($narrator === null) {
            return $this->takhrij ?? $this->sourceRef;
        }

        $line = PageStrings::of('narrated_by', $locale).' '.$narrator;

        if ($this->hadithNumber === null || trim($this->hadithNumber) === '') {
            return $line;
        }

        $number = $locale->isSource()
            ? Arabic::toArabicIndicDigits($this->hadithNumber)
            : $this->hadithNumber;

        return $line.PageStrings::of('comma', $locale).PageStrings::of('number', $locale).' '.$number;
    }

    /**
     * رابطُ الآية في quran.com — T-81، **بترجمة الصفحة نفسها**.
     *
     * ★ **والحديثُ رابطُ بحثٍ في الدرر السنية** — T-211، وثيقةُ المرجعية.
     * لا موضعٌ مخمَّن: لا مرجعَ واحداً ثابتاً لكتب الحديث كلّها، فالرابطُ
     * بحثٌ بمطلع المتن بلا تشكيل، يرى فيه القارئ أحكامَ المحدّثين بنفسه.
     * **ولا يجلب الخادمُ منها شيئاً**: الدرر تحجب الطلبات الآلية.
     */
    public function url(Locale $locale = Locale::Ar): ?string
    {
        if ($this->kind === 'hadith') {
            return $this->dorarUrl();
        }

        if ($this->kind !== 'ayah' || $this->surah === null || $this->ayah === null) {
            return null;
        }

        $position = $this->surah.'/'.$this->ayah
            .($this->ayahEnd !== null && $this->ayahEnd > $this->ayah ? '-'.$this->ayahEnd : '');

        $translation = $locale->quranTranslationId();

        return 'https://quran.com/'.$position.($translation === null ? '' : '?translations='.$translation);
    }

    /** بحثٌ في الموسوعة الحديثية بمطلع المتن: حروفٌ وفراغاتٌ لا غير. */
    private function dorarUrl(): ?string
    {
        $query = trim((string) preg_replace(
            ['/[^\p{L}\s]/u', '/\s+/u'],
            ['', ' '],
            Arabic::stripDiacritics($this->excerpt(6)),
        ));

        return $query === '' ? null : 'https://dorar.net/hadith/search?q='.rawurlencode($query);
    }

    /**
     * طرفُ الشاهد — أوّلُ كلماته، كصنيع كتب الأطراف (T-81).
     *
     * ★ **والقائمةُ حاشيةٌ تُراجَع لا متنٌ يُقرأ** (T-67): الشاهدُ بلفظه كاملاً
     * في موضعه من المتن، وهنا ما يُعرف به ويُهتدى إلى موضعه. **ولفظُ الطرف
     * لفظُ المصدر حرفاً** — يُقطع ولا يُبدَّل.
     *
     * وعلاماتُ الوقف ورمزُ الحزب تُحمل ولا تُعدّ كلمات، ولا يُختم الطرفُ
     * بإحداها. وما لا يزيد على الحدّ إلّا كلمةً يُعرض كاملاً: قطعُ كلمةٍ
     * واحدة يُضيّع أكثر ممّا يوفّر.
     */
    public function excerpt(int $words = 7): string
    {
        $isAyah = $this->kind === 'ayah';

        /*
         * ★ **ومطلعُ الحديث لا مطلعُ روايته** — T-116، بلاغُ مالك المنتج.
         *
         * فأوّلُ كلمات الحديث في المدوّنة إسنادُه أو صيغةُ روايته: «عَنْ
         * أَبِيهِ، قَالَ قَالَ رَسُولُ اللَّهِ ﷺ…» — تتشابه بها السطورُ كلُّها
         * فلا يُعرف منها حديثٌ من حديث، **وهي إنّما وُضعت ليُعرف**.
         *
         * **ولفظُه لفظُ المصدر حرفاً**: {@see MatnOpening} تدلّ على موضع
         * البداية وحده، ولا تُنشئ حرفاً ولا تُبدّل.
         */
        $plain = $isAyah ? AyahText::plain($this->text) : MatnOpening::of(trim($this->text));
        $tokens = preg_split('/\s+/u', $plain, -1, PREG_SPLIT_NO_EMPTY) ?: [];

        $isWord = static fn (string $token): bool => preg_match('/\p{L}/u', $token) === 1;

        if (count(array_filter($tokens, $isWord)) <= $words + 1) {
            /*
             * ★ **ولا يُختم الطرفُ بعلامة** — وهي قاعدةُ هذا الطرف نفسِها،
             * وكانت تُطبَّق على المقطوع وحده دون التامّ. فالمتنُ القصير يخرج
             * بعلامة إغلاق اقتباسه: «… وَجُبْنٌ خَالِعٌ " .» — تُقرأ اقتباساً
             * بلا فاتحة، إذ الفاتحةُ هي التي بدأ منها الطرف (T-116).
             */
            return $isAyah ? $this->text : self::withoutTrailingMarks($tokens, $isWord);
        }

        $kept = [];
        $counted = 0;

        foreach ($tokens as $token) {
            if ($counted === $words) {
                break;
            }

            $kept[] = $token;
            $counted += $isWord($token) ? 1 : 0;
        }

        $head = self::withoutTrailingMarks($kept, $isWord).'…';

        return $isAyah ? AyahText::OPEN.$head.AyahText::CLOSE : $head;
    }

    /**
     * الكلماتُ بلا ما خُتمت به من علاماتٍ — ولا تُنزع من وسطها.
     *
     * @param  list<string>  $tokens
     * @param  callable(string): bool  $isWord
     */
    private static function withoutTrailingMarks(array $tokens, callable $isWord): string
    {
        while ($tokens !== [] && ! $isWord((string) end($tokens))) {
            array_pop($tokens);
        }

        return implode(' ', $tokens);
    }

    public function gradeLabel(?Locale $locale = null): ?string
    {
        return PageStrings::grade($this->grade, $locale ?? Locale::Ar);
    }
}
