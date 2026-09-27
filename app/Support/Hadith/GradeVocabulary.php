<?php

declare(strict_types=1);

namespace App\Support\Hadith;

use App\Enums\HadithGrade;

/**
 * Maps the corpus's Latin ruling vocabulary onto {@see HadithGrade} — T-05ب.
 *
 * أحكام المدوّنة منقولة بحروف لاتينية من خمسة عشر محكِّماً، **وهي مفرداتٌ
 * محصورةٌ تُعدّ**: ستّة وستّون لفظاً بعد ردّ الإحالات المقوّسة. والجدول أدناه
 * حرفيٌّ لا اشتقاقي — **وما خرج عنه `Unknown` ولا يمرّ**.
 *
 * ولماذا حرفيّ؟ لأنّ التركيب بالكلمات يُنتج ما لم يُقصد: «Sahih» تمرّ،
 * و«Isnaad Sahih» لا تمرّ، والفرق بينهما كلمةٌ واحدة. وجدولٌ يُقرأ صفّاً
 * صفّاً يُراجَع، ومحلّلٌ يجمع الكلمات يُفاجئ.
 *
 * **والقاعدة الحاكمة: الإخفاق في الاتّجاه الآمن.**
 *
 * @see khulasah-build-spec.md §7-3
 */
final class GradeVocabulary
{
    /**
     * المفردات كلّها، مُقنَّنةً — لا يُزاد فيها إلّا بقياسٍ على ما قبله.
     *
     * **ما يمرّ:** الصحيح والحسن وتركيباتهما **الصريحة في المتن**.
     *
     * **وما لا يمرّ ولو حمل لفظ الصحّة:**
     *
     *   ١. `Isnaad Sahih` و`Sahih Isnaad` وأخواتهما — **صحّة الإسناد ليست
     *      صحّة المتن**، وبينهما فرق يعرفه أهل الفنّ. ويُنقض بأن يكون
     *      الإسناد صحيحاً والمتن شاذّاً أو معلّاً.
     *
     *   ٢. `Maqtu` و`Mauquf` و`Mursal` — **أوصاف إسناد لا أحكام قوّة**،
     *      وهي مع ذلك تُخرج الخبر عن أن يكون مرفوعاً إلى النبيّ ﷺ. فلا
     *      يُنشر على أنّه حديث ولو صحّ إسناده إلى قائله. ولذلك **كلّ لفظ
     *      حمل أحدها مجهولٌ عندنا** مهما اقترن به من صحّة أو ضعف.
     *
     *   ٣. المتعارض في نفسه (`Shadh, Sahih`) وما غمض (`Sahih Witness …`).
     *
     * **وما يُنقل ضعفه يُقبل ضعفه:** `Daif Isnaad` و`Sanad Daif` و
     * `Isnaad Malool` تُقرأ ضعفاً وإن كانت في الإسناد. والاتّجاه الآمن غير
     * متماثل: **الإمرار يحتاج صحّة المتن، والحبس يكفيه شكّ**.
     *
     * @var array<string, HadithGrade>
     */
    private const TABLE = [
        // ── يمرّ: صحيح ──────────────────────────────────────────────
        'sahih' => HadithGrade::Sahih,
        'sahih hadith' => HadithGrade::Sahih,
        'sahih matn' => HadithGrade::Sahih,
        'sahih mutawatir' => HadithGrade::Sahih,
        'sahih lighairihi' => HadithGrade::Sahih,
        // إحالات المحكِّم إلى الصحيحين — وهي عين الاستثناء المعتمد.
        'sahih - agreed upon' => HadithGrade::Sahih,
        'sahih - bukhari and muslim' => HadithGrade::Sahih,
        'sahih bukhari' => HadithGrade::Sahih,
        'sahih muslim' => HadithGrade::Sahih,
        'sahih bukhari sahih muslim' => HadithGrade::Sahih,

        // ── يمرّ: حسن ───────────────────────────────────────────────
        'hasan' => HadithGrade::Hasan,
        'hasan lighairihi' => HadithGrade::Hasan,
        // «حسن صحيح» اصطلاح الترمذي، ويُؤخذ فيه بالأدنى: حسن.
        'hasan sahih' => HadithGrade::Hasan,

        // ── لا يمرّ: ضعيف ───────────────────────────────────────────
        'daif' => HadithGrade::Daif,
        'very daif' => HadithGrade::Daif,
        'daif isnaad' => HadithGrade::Daif,
        'isnaad daif' => HadithGrade::Daif,
        'sanad daif' => HadithGrade::Daif,
        'very daif isnaad' => HadithGrade::Daif,
        'isnaad malool' => HadithGrade::Daif,
        'shadh' => HadithGrade::Daif,
        'munkar' => HadithGrade::Daif,
        'daif munkar' => HadithGrade::Daif,
        'munkar daif' => HadithGrade::Daif,

        // ── لا يمرّ: موضوع ──────────────────────────────────────────
        'mawdu' => HadithGrade::Mawdu,
        'batil' => HadithGrade::Mawdu,

        // ── لا يمرّ: صحّة إسنادٍ لا صحّة متن ────────────────────────
        'isnaad sahih' => HadithGrade::Unknown,
        'sahih isnaad' => HadithGrade::Unknown,
        'isnaad hasan' => HadithGrade::Unknown,
        'hasan isnaad' => HadithGrade::Unknown,
        'hasan sahih isnaad' => HadithGrade::Unknown,
        'isnaad sahih agreed upon' => HadithGrade::Unknown,
        'isnaad sahih bukhari and muslim' => HadithGrade::Unknown,
        'isnaad sahih sahih bukhari' => HadithGrade::Unknown,
        'isnaad sahih sahih muslim' => HadithGrade::Unknown,
        'isnaad sahih sahih bukhari sahih muslim' => HadithGrade::Unknown,
        'isnaad hasan sahih bukhari' => HadithGrade::Unknown,
        'isnaad hasan sahih muslim' => HadithGrade::Unknown,
        'isnaad hasan sahih bukhari sahih muslim' => HadithGrade::Unknown,
        'sahih - isnaad hasan bukhari and muslim' => HadithGrade::Unknown,

        // ── لا يمرّ: موقوف ومقطوع ومرسل — ليست مرفوعةً إلى النبيّ ﷺ ──
        'maqtu' => HadithGrade::Unknown,
        'maqtu sahih' => HadithGrade::Unknown,
        'maqtu hasan' => HadithGrade::Unknown,
        'hasan maqtu' => HadithGrade::Unknown,
        'maqtu daif' => HadithGrade::Unknown,
        'maqtu sahih lighairihi' => HadithGrade::Unknown,
        'sahih maqtu' => HadithGrade::Unknown,
        'daif maqtu' => HadithGrade::Unknown,
        'sahih isnaad maqtu' => HadithGrade::Unknown,
        'daif isnaad maqtu' => HadithGrade::Unknown,
        'mauquf' => HadithGrade::Unknown,
        'mauquf sahih' => HadithGrade::Unknown,
        'mauquf hasan' => HadithGrade::Unknown,
        'mauquf daif' => HadithGrade::Unknown,
        'mauquf munkar' => HadithGrade::Unknown,
        'mauquf sahih lighairihi' => HadithGrade::Unknown,
        'mauquf hasan lighairihi' => HadithGrade::Unknown,
        'sahih muquf' => HadithGrade::Unknown,
        'daif muquf' => HadithGrade::Unknown,
        'sahih isnaad mauquf' => HadithGrade::Unknown,
        'mursal' => HadithGrade::Unknown,
        'mursal sahih isnaad' => HadithGrade::Unknown,
        'sahih isnaad mursal' => HadithGrade::Unknown,

        // ── لا يمرّ: متعارض أو غامض أو فارغ ─────────────────────────
        'shadh, sahih' => HadithGrade::Unknown,
        'sahih witness sahih muslim' => HadithGrade::Unknown,
        '-' => HadithGrade::Unknown,
    ];

    /**
     * ترتيب الشدّة عند اختلاف المحكِّمين.
     *
     * **و`Unknown` ليست في الترتيب عمداً** — فهي غياب حكمٍ لا حكم. ولو
     * عُدّت أشدّ الجميع لنقض المحكِّمَ الذي صرّح بحكمٍ محكِّمٌ لم يُقرأ لفظه،
     * ولضاع حكمٌ منصوص بلفظٍ غامض. فتُهمَل ما دام في الصفّ حكمٌ مقروء،
     * ولا تعود إلّا إذا لم يُقرأ في الصفّ حكم البتّة.
     *
     * @var array<string, int>
     */
    private const SEVERITY = [
        'mawdu' => 4,
        'daif' => 3,
        'hasan' => 2,
        'sahih' => 1,
    ];

    private function __construct() {}

    /**
     * تقنين اللفظ قبل البحث في الجدول.
     *
     * **وتُحذف الإحالات المقوّسة** — «Sahih Bukhari (1023) Sahih Muslim (894)»
     * إحالةٌ إلى رقمَي الحديث في الصحيحين، ولولا حذفها لصار كلُّ رقمٍ لفظاً
     * جديداً وانفجرت المفردات إلى ألفٍ وستّمئة بدل ستّةٍ وستّين.
     */
    public static function canonicalize(string $raw): string
    {
        $value = (string) preg_replace('/\s*\([^)]*\)/u', '', $raw);
        $value = (string) preg_replace('/\s+/u', ' ', $value);

        return mb_strtolower(trim($value));
    }

    /** اللفظ الواحد — وما خرج عن الجدول مجهول. */
    public static function map(?string $raw): HadithGrade
    {
        if ($raw === null) {
            return HadithGrade::Unknown;
        }

        return self::TABLE[self::canonicalize($raw)] ?? HadithGrade::Unknown;
    }

    /** أفي الجدول لفظٌ بهذا الاسم؟ — يستعمله البذر ليعدّ ما لم يُقرأ. */
    public static function knows(string $raw): bool
    {
        return array_key_exists(self::canonicalize($raw), self::TABLE);
    }

    /**
     * **يُؤخذ بأشدّهم** — المواصفة §7-3 البند ٤.
     *
     * الصفّ الواحد يحمل أحكاماً متعارضة، فيقول الألباني «شاذّ» ويقول زبير
     * علي زئي «إسناده صحيح». **والأخذ بأليَنها في متنٍ شرعيّ اختيارٌ للأسهل
     * لا للأصحّ** — فيُؤخذ بالشاذّ، وهو ضعيف.
     *
     * @param  list<array{name?: string, grade?: string}>  $grades
     * @return array{HadithGrade, ?string} الحكم، واللفظ الذي جاء منه.
     */
    public static function strictest(array $grades): array
    {
        $chosen = HadithGrade::Unknown;
        $chosenRaw = null;
        $severity = 0;

        foreach ($grades as $entry) {
            $raw = $entry['grade'] ?? null;

            if (! is_string($raw) || trim($raw) === '') {
                continue;
            }

            $grade = self::map($raw);

            if ($grade === HadithGrade::Unknown) {
                continue;
            }

            $rank = self::SEVERITY[$grade->value];

            if ($rank > $severity) {
                $severity = $rank;
                $chosen = $grade;
                $chosenRaw = trim($raw);
            }
        }

        return [$chosen, $chosenRaw];
    }
}
