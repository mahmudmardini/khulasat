<?php

declare(strict_types=1);

namespace App\Services\Verification;

use App\Contracts\EvidenceVerifier;
use App\Contracts\HadithProvider;
use App\Enums\HadithBook;
use App\Enums\HadithGrade;
use App\Enums\MatchStatus;
use App\Support\Arabic;
use App\Support\Hadith\NarrationFormulas;
use App\Support\Hadith\Takhrij;
use App\Support\Verification\DomainPolicy;
use App\Support\Verification\EvidenceInput;
use App\Support\Verification\HadithMatch;
use App\Support\Verification\VerificationResult;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Matches a quoted hadith against its sources — المواصفة §7-3.
 *
 * يفترق عن `QuranVerifier` في أنّ الحديث **يُروى بالمعنى**، فلا تُطلب فيه
 * المطابقة الحرفية. ولذلك عتبات تشابه، لا مطابقة أو لا شيء.
 *
 * **والقاعدة الحاكمة فوق العتبات كلّها:** الحديث الذي حكمه ضعيف أو موضوع
 * أو لا حكم له **يُرفع للمراجعة مهما بلغ تطابقه**. فالمطابقة تقول «هذا
 * اللفظ موجود»، لا «هذا اللفظ صحيح»، وهما سؤالان مختلفان. وهذا الفحص
 * **مستقلّ بعد حساب التشابه لا شرطٌ داخله** — حتى لا يُلغى بتعديل عتبة.
 */
class HadithVerifier implements EvidenceVerifier
{
    /** أقلّ عدد كلمات تُعدّ به الشذرة اقتباساً لا لفظاً عابراً. */
    private const MIN_FRAGMENT_WORDS = 4;

    /** وأقلّ ما تغطّيه من لفظ المصدر. */
    private const MIN_FRAGMENT_COVERAGE = 0.6;

    /** وطولٌ يُغني عن النسبة، إذ يخرج المتطابق به عن أن يكون مصادفة. */
    private const CITABLE_WORDS = 6;

    /**
     * @param  list<HadithProvider>  $providers
     * @param  DomainPolicy  $policy  عتبات مجاله وما يمرّ فيه — §7-5. وهي
     *                                من المجال لا من إعدادٍ عامّ، فمجالٌ آخر
     *                                يقبل إعادة الصياغة ما دام المرجع صحيحاً.
     */
    public function __construct(
        private readonly array $providers,
        private readonly DomainPolicy $policy,
    ) {}

    public function verify(EvidenceInput $input): VerificationResult
    {
        $needle = Arabic::normalize($input->rawText);

        if ($needle === '') {
            return VerificationResult::none();
        }

        // ★ **صيغةُ الرواية ليست حديثاً** — T-06ب القرار الرابع.
        //   «قال رسول الله صلى الله عليه وسلم» في آلاف الصفوف، فتُطابَق
        //   تامّةً وتُنشر حديثاً صحيحاً بتخريجٍ كامل. ويُفحص هنا **قبل
        //   المزوّدين**: السؤال عن المستخرَج نفسه، لا عمّا يشبهه.
        if (NarrationFormulas::isFormulaic($needle)) {
            return VerificationResult::none();
        }

        $best = $this->bestCandidate($needle);

        if ($best === null) {
            return VerificationResult::none();
        }

        [$candidate, $similarity, $exactBooks] = $best;

        $status = $this->statusFor($similarity);

        if ($status === MatchStatus::None) {
            return VerificationResult::none();
        }

        /*
         * ★ **والطبقةُ الثانية لا يُقبل منها إلّا التامّ** — T-170. لا حكمَ فيها
         * يُبيَّن، فالمقاربةُ منها لا تفيد إلّا تقريبَ المحرَّف إلى أقرب نصّ:
         * «أحبّ الأعمال إلى الله أكثرُها» (`H-ALTERED-01`) تقع «قريبةً» من لفظٍ
         * في المسند وهي محرّفة. والمقاربةُ تُقبل من الكتب المحكومة وحدها.
         */
        if ($candidate->bookKey?->isSecondary() && $status !== MatchStatus::Exact) {
            return VerificationResult::none();
        }

        $grade = $candidate->resolvedGrade();
        $books = $this->matchedBooks($candidate, $exactBooks);
        $takhrij = $books === [] ? $candidate->takhrij : Takhrij::forBooks($books);
        $disclosure = Takhrij::disclosure($books, $grade);

        return new VerificationResult(
            status: $status,
            // عند المطابقة التامّة يُثبَّت لفظ المصدر. وعند الجزئية يُعرض
            // اللفظان معاً ليحكم المراجع — SCREENS.md §5.
            matchedText: $candidate->text,
            // **المرجع كما يُنشر: النسبة والرقم مقرونين بالدرجة** — §7-5.
            sourceRef: $this->reference($candidate, $books, $grade, $takhrij),
            sourceMeta: [
                'narrator' => $candidate->narrator,
                'book' => $candidate->book,
                'hadith_number' => $candidate->hadithNumber,
                'grade' => $grade->value,
                'grade_label' => $grade->label(),
                'grade_raw' => $candidate->ruling,
                'takhrij' => $takhrij,
                // ★ **الجملة التي تُنشر** — §7-5. اللفظ مقروناً بدرجته.
                'disclosure' => $disclosure,
                // ★ **الضمانة الحاكمة**: لا يُنشر شاهد بلا درجة منصوصة.
                //   والمجهول ليس درجة — وعجزُنا عن التخريج ليس حكماً بالوضع،
                //   فيُحذف صامتاً ولا يُوصَف بشيء.
                'has_stated_grade' => $grade !== HadithGrade::Unknown,
                // **كلّ المحكِّمين كما قالوا** — §7-3 البند ٤. المأخوذ به
                // أشدُّهم، ويبقى الخلاف مرئيّاً للمراجع لا مطويّاً عنه.
                'graders' => $candidate->graders,
                'similarity' => round($similarity, 4),
                // اقتباس لبعض الحديث لا لكلّه — يُذكر في التخريج ولا يُخفى.
                'is_fragment' => count(explode(' ', $needle))
                    < count(explode(' ', Arabic::normalize($candidate->text))),
                'extracted_text' => $input->rawText,
                'claimed_narrator' => $input->claimedNarrator,
                // الراوي المدّعى يخالف المصدر — يُعرض ولا يُصحَّح صامتاً.
                'narrator_corrected' => $this->narratorDiffers($input, $candidate),
                // درجةٌ لا تمرّ بلا بيان — يراها المراجع في وضع `review`.
                'held_for_grade' => ! $grade->mayAutoPass(),
            ],
        );
    }

    /**
     * أفضل مرشّح من المزوّدين بالترتيب، ومعه الكتب التي طابقها المتن تماماً.
     *
     * **ولا كاش هنا.** كان بين المحقّق والمزوّد جدول `hadith_cache` يمنع
     * نداءً خارجياً يتكرّر. ولم يبقَ نداء خارجي: المدوّنة في قاعدتنا، فصار
     * الكاش طبقةً بين الشيء ونفسه — أبطأَ من الاستعلام الذي يوفّره، وطبقةَ
     * تقادمٍ زائدة على بيانات لا تتغيّر إلّا ببذرٍ نُجريه نحن.
     *
     * @return array{HadithMatch, float, list<HadithBook>}|null
     */
    private function bestCandidate(string $needle): ?array
    {
        $best = null;
        $exactBooks = [];
        $exactThreshold = $this->threshold('exact');

        foreach ($this->providers as $provider) {
            try {
                $matches = $provider->search($needle);
            } catch (Throwable $e) {
                // سقوط مزوّد لا يُسقط النظام — المواصفة §7-4.
                Log::warning('مزوّد حديث أخفق', [
                    'provider' => $provider->name(),
                    'error' => $e->getMessage(),
                ]);

                continue;
            }

            foreach ($matches as $match) {
                $similarity = $this->similarity($needle, Arabic::normalize($match->text));

                // **«متّفقٌ عليه» تُحسب لا تُنقل:** يُجمَع كلّ كتابٍ بلغ لفظُه
                // حدَّ المطابقة التامّة، فإن كان فيها الصحيحان فقد اتّفقا.
                // ولا يكفي أفضلُ مرشّح وحده، فهو من كتابٍ واحد بالضرورة.
                if ($similarity >= $exactThreshold && $match->bookKey !== null) {
                    $exactBooks[] = $match->bookKey;
                }

                if ($best === null || $similarity > $best[1]) {
                    $best = [$match, $similarity];
                }
            }

            // ★ **أوّلُ مزوّدٍ يبلغ حدَّ المطابقة الجزئية يُعتمد**، ولا يُسأل من
            // بعده — T-170. وكان الحدُّ التامّة، والمزوّدُ واحد. فلمّا صار بعده
            // مسندُ أحمد بلا أحكام، صار سؤالُه بعد مطابقةٍ جزئيةٍ في الصحيحين
            // يُبدّل نسخةً محكوماً عليها بنسخةٍ لا حكم لها لأنّها أقربُ لفظاً.
            if ($best !== null && $best[1] >= $this->threshold('partial')) {
                break;
            }
        }

        return $best === null ? null : [$best[0], $best[1], $exactBooks];
    }

    /**
     * الكتب التي يُبنى عليها التخريج **من فهرسنا** — المواصفة §7-3.
     *
     * ويُقدَّم المحسوب على المنقول: صفُّ المدوّنة يحمل «رواه البخاري» مبنيّةً
     * وقت البذر من كتابه، وهذه تُصعّدها إلى «متّفقٌ عليه» إن طابق المتنُ
     * الصحيحين معاً — وهو ما لا يعرفه صفٌّ واحد عن نفسه.
     *
     * @param  list<HadithBook>  $exactBooks
     * @return list<HadithBook>
     */
    private function matchedBooks(HadithMatch $candidate, array $exactBooks): array
    {
        if ($exactBooks !== []) {
            // **بلا تكرار**: الحديث يرد بألفاظ متقاربة في صفوفٍ من الكتاب
            // نفسه، وعدُّها كتباً يجعل «رواه البخاري» تُقرأ كتابين فيسقط
            // الرقم من المرجع بلا سبب.
            return array_values(array_unique($exactBooks, SORT_REGULAR));
        }

        return $candidate->bookKey !== null ? [$candidate->bookKey] : [];
    }

    /**
     * تشابه على المطبَّع — المواصفة §7-3 البند 4.
     *
     * يُقاس على مستوى الكلمة لا الحرف: الحديث يُروى بالمعنى، وزيادة كلمة
     * أو نقصها تغيير جوهري، أمّا اختلاف حرفٍ داخل كلمة فقد سبق أن ابتلعه
     * التطبيع. ومسافةُ تحريرٍ حرفية تُعطي زيادةَ كلمةٍ درجةً عالية كاذبة.
     *
     * **ويُقاس على أفضل نافذة داخل لفظ المصدر لا على المصدر كلّه**، لأنّ
     * المتكلّم يقتبس الحديث ناقصاً صدره أو عجزه — يقول «من سلم المسلمون من
     * لسانه ويده» ولفظ البخاري «المسلم من سلم…». وقياسُه على النصّ كلّه
     * يحسب صدراً لم يُقتبس تحريفاً، فيُنزل اللفظ الصحيح إلى `partial`
     * ويُغرق المراجع بما لا يحتاج مراجعة — عيّنة القبول `H-EXACT-02`.
     */
    private function similarity(string $a, string $b): float
    {
        if ($a === $b) {
            return 1.0;
        }

        $quote = explode(' ', $a);
        $source = explode(' ', $b);

        if ($quote === [''] || $source === ['']) {
            return 0.0;
        }

        // الشذرة القصيرة تُطابَق دائماً لأنّها موجودة في كلّ نصّ تقريباً.
        // فـ«ويده» وحدها نافذةٌ تامّة داخل الحديث، وليست اقتباساً له.
        if (! $this->citableFragment(count($quote), count($source))) {
            return 0.0;
        }

        $distance = $this->windowDistance($quote, $source);

        return max(0.0, 1 - ($distance / count($quote)));
    }

    /**
     * أتبلغ الشذرة أن تُعدّ اقتباساً؟ — قرار مالك المنتج، ٦ أيلول ٢٠٢٦.
     *
     * ثلاثة أبواب، يكفي أن يُفتح واحد:
     *
     *   ١. **ما ساوى المصدر أو زاد عليه** اقتباسٌ تامّ يغطّيه كلّه —
     *      و«إنما الأعمال بالنيات» ثلاث كلمات وهو حديث تامّ.
     *   ٢. **أو غطّى ٦٠٪ من لفظ المصدر وبلغ أربع كلمات** — وهما حدّا مالك
     *      المنتج: النسبة وحدها لا تكفي في حديث طويل، والعدد وحده لا يكفي
     *      في حديث قصير.
     *   ٣. **أو طال حتى خرج عن أن يكون مصادفة.**
     *
     * والباب الثالث أُضيف في T-05ب لأنّ المدوّنة المبذورة **تحمل الإسناد مع
     * المتن**: صفُّ البخاري الأوّل ستّون كلمة، متنُه منها ثمانٍ. فنسبة أيّ
     * اقتباس صحيح إلى الصفّ كلّه دون العُشر، **فكان الحدّ الثاني يردّ كلّ
     * اقتباس في الدنيا** — والمدوّنة لا تطابق شيئاً البتّة. وعزلُ المتن عن
     * الإسناد لا سبيل إليه بعلامة: علامة الاقتباس في المصدر لا تَرِد إلّا في
     * ٦٠٪ من الصفوف.
     *
     * ★ **والعدد ستّة مؤقّت، ويُحسم في T-06ب** — فسبع كلمات هي طول «قال رسول
     * الله صلى الله عليه وسلم»، وهي في آلاف الصفوف. فالفاصل الحقيقي بين
     * الاقتباس وعبارة الرواية ليس الطول بل معرفةُ العبارات المطّردة، وذلك
     * قرارُ تلك المهمّة على عيّنة `H-*` نفسها.
     */
    private function citableFragment(int $quoteWords, int $sourceWords): bool
    {
        if ($quoteWords >= $sourceWords || $quoteWords >= self::CITABLE_WORDS) {
            return true;
        }

        return $quoteWords >= self::MIN_FRAGMENT_WORDS
            && ($quoteWords / $sourceWords) >= self::MIN_FRAGMENT_COVERAGE;
    }

    /**
     * Word-level edit distance, free to skip the source's head and tail.
     *
     * دالّة PHP المدمجة تعمل على البايتات، فتُفسد العربية متعدّدة البايتات
     * وتقيس على الحرف لا الكلمة. وهذه تحسب الإدراج والحذف والإبدال على
     * الكلمات، بصفّين لا بمصفوفة كاملة.
     *
     * والفرق عن المسافة العامّة أنّ صفّ البداية أصفار وأنّ الجواب أصغرُ ما
     * في الصفّ الأخير: أي أنّ تخطّي صدر المصدر وعجزه بلا كلفة، وكلّ ما
     * سواه محسوب. فالاقتباس يُقاس على ما اقتُبس.
     *
     * @param  list<string>  $quote  اللفظ المستخرج — يُستهلك كلّه.
     * @param  list<string>  $source  لفظ المصدر — يجوز تخطّي طرفيه.
     */
    private function windowDistance(array $quote, array $source): int
    {
        $n = count($quote);
        $m = count($source);

        // بداية حرّة: تخطّي صدر المصدر بلا كلفة.
        $previous = array_fill(0, $m + 1, 0);

        for ($i = 1; $i <= $n; $i++) {
            $current = [$i];

            for ($j = 1; $j <= $m; $j++) {
                $current[$j] = min(
                    $previous[$j] + 1,                                          // حذف
                    $current[$j - 1] + 1,                                       // إدراج
                    $previous[$j - 1] + ($quote[$i - 1] === $source[$j - 1] ? 0 : 1), // إبدال
                );
            }

            $previous = $current;
        }

        // نهاية حرّة: تخطّي عجز المصدر بلا كلفة.
        return min($previous);
    }

    private function statusFor(float $similarity): MatchStatus
    {
        return match (true) {
            $similarity >= $this->threshold('exact') => MatchStatus::Exact,
            $similarity >= $this->threshold('partial') => MatchStatus::Partial,
            default => MatchStatus::None,
        };
    }

    private function threshold(string $key): float
    {
        return $this->policy->threshold($key);
    }

    private function narratorDiffers(EvidenceInput $input, HadithMatch $candidate): bool
    {
        if (blank($input->claimedNarrator) || blank($candidate->narrator)) {
            return false;
        }

        return ! str_contains(
            Arabic::normalize($candidate->narrator),
            Arabic::normalize($input->claimedNarrator),
        );
    }

    /**
     * المرجع كما يُعرض ويُنشر.
     *
     * **ولا يُذكر رقمٌ مع تخريجٍ لأكثر من كتاب.** «متّفقٌ عليه: رواه البخاري
     * ومسلم، رقم ١٠» تُقرأ أنّ العشرة رقمُه عندهما جميعاً، وهو خطأ: الرقم
     * رقمُ الصفّ الذي طابق أعلى، لا رقمٌ مشترك. فيبقى في `source_meta` مع
     * كتابه ليراه المراجع، ولا يُنشر في سطرٍ يُوهم.
     */
    private function reference(HadithMatch $candidate, array $books, HadithGrade $grade, ?string $takhrij): string
    {
        $number = $candidate->hadithNumber !== null && count($books) < 2
            ? 'رقم '.Arabic::toArabicIndicDigits($candidate->hadithNumber)
            : null;

        $disclosed = Takhrij::disclosure($books, $grade, $number);

        if ($disclosed !== '') {
            return $disclosed;
        }

        // مزوّدٌ لا يعرف كتبنا (كالوهمي في الاختبارات)، أو درجةٌ مجهولة
        // فلا جملةَ بيانٍ لها. ويبقى ما يُعرَض على المراجع.
        return implode('، ', array_filter([$takhrij ?: $candidate->book, $number]));
    }
}
