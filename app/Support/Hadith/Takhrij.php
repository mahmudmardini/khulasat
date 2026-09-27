<?php

declare(strict_types=1);

namespace App\Support\Hadith;

use App\Enums\HadithBook;
use App\Enums\HadithGrade;

/**
 * Builds the Arabic attribution line from our own index — T-05ب.
 *
 * **لا يُنقل نثر التخريج من مصدر.** المدوّنة المعتمدة لا تحمل جملة تخريج
 * أصلاً، وهذا خيرٌ لا نقص: التخريج عندنا **يُشتقّ من الفهرس** — «رواه أبو
 * داود» من الكتاب الذي جاء منه الصفّ، لا من نصٍّ نأخذه على علّاته.
 *
 * و«متّفقٌ عليه» **تُحسب ولا تُنقل**: إن طابق المتنُ صفّاً في البخاري وصفّاً
 * في مسلم فقد اتّفقا عليه. وهي في المدوّنة غير موجودة البتّة، إذ الصحيحان
 * جاءا بلا أحكام ولا تخريج.
 *
 * @see khulasah-build-spec.md §7-3
 */
final class Takhrij
{
    private function __construct() {}

    /** «رواه البخاري» — من كتابٍ واحد. */
    public static function forBook(HadithBook $book): string
    {
        return 'رواه '.$book->narratedBy();
    }

    /**
     * التخريج من الكتب التي طابقها المتن.
     *
     * **والصحيحان معاً «متّفقٌ عليه»** — وهي أعلى ما يُقال في تخريج حديث،
     * فتُقدَّم على تعداد الكتب. وما اجتمع فيه الصحيحان وغيرهما فالاتّفاق
     * هو الخبر، وذكرُ من دونهما بعده فضول.
     *
     * @param  list<HadithBook>  $books
     */
    public static function forBooks(array $books): string
    {
        $books = self::unique($books);

        if ($books === []) {
            return '';
        }

        $hasBukhari = in_array(HadithBook::Bukhari, $books, true);
        $hasMuslim = in_array(HadithBook::Muslim, $books, true);

        if ($hasBukhari && $hasMuslim) {
            return 'متّفقٌ عليه: رواه البخاري ومسلم';
        }

        $names = array_map(static fn (HadithBook $b): string => $b->narratedBy(), $books);

        return 'رواه '.self::conjoin($names);
    }

    /**
     * ★ **التخريج المصاحب** — المواصفة §7-5، جدول سياسة البيان.
     *
     * > يُنشر ما عُرف مصدره **مقروناً بدرجته**، ولا يُنشر ما جُهل مصدره.
     *
     * وهذه هي الجملة التي تُنشر إلى جانب اللفظ. **والضعيف يُنشر مبيَّناً**،
     * إذ الآفة في نقله موهِماً صحّته لا في نقله مبيَّناً — وهذا عمل أهل العلم.
     *
     * **وإخراج الشيخين يُغني عن جملة الدرجة**، فهو نفسه الدرجة المنصوصة
     * (§7-3، وتنبيه §7-5 البند ٢). أمّا صحيحٌ في غيرهما فيُصرَّح بصحّته،
     * إذ «رواه الترمذي» وحدها لا تقول شيئاً عن درجته.
     *
     * **والرقم يدخل في النسبة لا بعد البيان**: «رواه ابن ماجه، رقم ٢٢٤ —
     * وإسناده ضعيف» تُقرأ، و«رواه ابن ماجه — وإسناده ضعيف، رقم ٢٢٤» تُوهم
     * أنّ الرقم رقمُ الحكم.
     *
     * @param  list<HadithBook>  $books
     * @param  ?string  $number  «رقم ٦٤٦٤» إن كان المصدر كتاباً واحداً.
     */
    public static function disclosure(array $books, HadithGrade $grade, ?string $number = null): string
    {
        $attribution = self::forBooks($books);

        if ($attribution !== '' && $number !== null && $number !== '') {
            $attribution .= '، '.$number;
        }

        if ($grade === HadithGrade::Unknown) {
            // **ولا يُنشر أصلاً.** وما لا درجة له لا يُوصَف بشيء، فلا جملة له.
            return '';
        }

        if ($grade === HadithGrade::Mawdu) {
            return $attribution === ''
                ? 'لا يصحّ — حكم عليه أهل العلم بالوضع'
                : "{$attribution} — ولا يصحّ، حكم عليه أهل العلم بالوضع";
        }

        if ($attribution === '') {
            return '';
        }

        $sahihayn = array_filter($books, static fn (HadithBook $b): bool => $b->isSahihayn());

        if ($grade === HadithGrade::Sahih && $sahihayn !== []) {
            return $attribution;
        }

        return $attribution.' — '.match ($grade) {
            HadithGrade::Sahih => 'وهو صحيح',
            HadithGrade::Hasan => 'وهو حسن',
            HadithGrade::Daif => 'وإسناده ضعيف',
            default => '',
        };
    }

    /**
     * «البخاري ومسلم وأبو داود» — الواو تسبق كلّ اسم بعد الأوّل.
     *
     * والعربية لا تفصل بفاصلة كما تفعل الإنجليزية، بل تعطف بالواو كلَّ مرّة.
     *
     * @param  list<string>  $names
     */
    private static function conjoin(array $names): string
    {
        $first = array_shift($names);

        return $names === []
            ? (string) $first
            : $first.implode('', array_map(static fn (string $n): string => ' و'.$n, $names));
    }

    /**
     * @param  list<HadithBook>  $books
     * @return list<HadithBook>
     */
    private static function unique(array $books): array
    {
        $seen = [];

        foreach ($books as $book) {
            $seen[$book->value] = $book;
        }

        // ترتيب الكتب ترتيبَ الفهرس لا ترتيبَ ورودها في المطابقة، فلا
        // يتبدّل نصّ التخريج بتبدّل ترتيب صفوف قاعدة البيانات.
        return array_values(array_filter(
            HadithBook::all(),
            static fn (HadithBook $b): bool => isset($seen[$b->value]),
        ));
    }
}
