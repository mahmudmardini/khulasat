<?php

declare(strict_types=1);

namespace App\Support\Quran;

/**
 * محاذاةُ اقتباسٍ من الحفظ على لفظ المصحف، كلمةً بكلمة — T-169.
 *
 * المحاضرُ يقرأ من حفظه، والمفرِّغُ يكتب ما سمع: فتسقط كلمة، أو تنفصل همزةٌ
 * عن كلمتها («أ يحب»)، أو تتكرّر كلمة. والمطابقةُ الحرفية تردّ الآية كلَّها
 * بسببها، وهي في المصحف. فهذه المحاذاةُ **حتميةٌ بلا نموذج** (CLAUDE.md §2،
 * القاعدة الثالثة)، وتقبل ثلاثة أشياء وحدها:
 *
 *   ١. **سقوطَ** كلمةٍ من لفظ المصحف داخل الموضع المقتبَس — بحدٍّ أقصى.
 *   ٢. **انفصالَ** كلمةٍ أو **اتّصالَ** كلمتين — مقارنةً بعد حذف المسافة.
 *   ٣. **تكرارَ** الكلمة السابقة.
 *
 * ★ **ولا تقبل التبديل.** كلُّ كلمةٍ في الاقتباس يجب أن تكون في لفظ المصحف
 * بموضعها، فكلمةٌ واحدةٌ ليست فيه تُسقط المحاذاة كلَّها — فيبقى «مسلم» بدل
 * «مؤمن» (`Q-ALTERED-01`) غيرَ مطابق كما كان.
 */
final class TolerantAlignment
{
    /**
     * @param  list<string>  $quote  كلماتُ الاقتباس مطبَّعة
     * @param  list<string>  $source  كلماتُ المصحف مطبَّعة، لآيةٍ أو آيتين
     * @return array{start: int, end: int, dropped: list<string>, joined: int, repeated: int}|null
     *                                                                                             أقلُّ المحاذاة سقوطاً، و`start`/`end` موضعا أوّل كلمةٍ وآخرِها في المصحف
     */
    public static function align(array $quote, array $source, int $maxDropped): ?array
    {
        if ($quote === [] || $source === []) {
            return null;
        }

        $best = null;

        // نتائجُ الطريق لا تتعلّق بموضع البدء، فتُحفظ مرّةً للمواضع كلّها.
        $memo = [];

        // ما قبل أوّل كلمةٍ مقتبَسة ليس سقوطاً: الاقتباسُ يبدأ حيث شاء من الآية.
        // **ولا يبدأ إلّا بكلمةٍ يُفتتح بها**: مثلُها، أو كلمتان كتبهما واحدة،
        // أو نصفُها — فلا تُجرَّب المحاذاةُ من كلّ كلمةٍ في الآية.
        for ($start = 0; $start < count($source); $start++) {
            if (! self::opens($quote, $source, $start)) {
                continue;
            }

            $path = self::walk($quote, $source, 0, $start, $maxDropped, $memo, started: false);

            if ($path !== null && ($best === null || $path['cost'] < $best['cost'])) {
                $best = $path + ['start' => $start];
            }
        }

        if ($best === null) {
            return null;
        }

        return [
            'start' => $best['start'],
            'end' => $best['end'],
            'dropped' => $best['dropped'],
            'joined' => $best['joined'],
            'repeated' => $best['repeated'],
        ];
    }

    /**
     * @param  list<string>  $quote
     * @param  list<string>  $source
     */
    private static function opens(array $quote, array $source, int $j): bool
    {
        return $quote[0] === $source[$j]
            || ($j + 1 < count($source) && $quote[0] === $source[$j].$source[$j + 1])
            || (count($quote) > 1 && $source[$j] === $quote[0].$quote[1]);
    }

    /**
     * @param  list<string>  $quote
     * @param  list<string>  $source
     * @param  array<string, array<string, mixed>|null>  $memo
     * @return array{cost: int, end: int, dropped: list<string>, joined: int, repeated: int}|null
     */
    private static function walk(array $quote, array $source, int $i, int $j, int $budget, array &$memo, bool $started): ?array
    {
        if ($i === count($quote)) {
            // ما بعد آخر كلمةٍ مقتبَسة ليس سقوطاً كذلك.
            return ['cost' => 0, 'end' => $j - 1, 'dropped' => [], 'joined' => 0, 'repeated' => 0];
        }

        if ($j >= count($source) && ! ($i > 0 && $quote[$i] === $quote[$i - 1])) {
            return null;
        }

        $key = $i.':'.$j.':'.$budget.':'.(int) $started;

        if (array_key_exists($key, $memo)) {
            return $memo[$key];
        }

        $options = [];

        if ($j < count($source)) {
            // الكلمةُ نفسها.
            if ($quote[$i] === $source[$j]) {
                $options[] = self::then(self::walk($quote, $source, $i + 1, $j + 1, $budget, $memo, true));
            }

            // كلمتان في المصحف كتبهما الاقتباسُ كلمةً واحدة.
            if ($j + 1 < count($source) && $quote[$i] === $source[$j].$source[$j + 1]) {
                $options[] = self::then(self::walk($quote, $source, $i + 1, $j + 2, $budget, $memo, true), joined: 1);
            }

            // كلمةٌ في المصحف فصلها الاقتباسُ كلمتين — «أ يحب».
            if ($i + 1 < count($quote) && $source[$j] === $quote[$i].$quote[$i + 1]) {
                $options[] = self::then(self::walk($quote, $source, $i + 2, $j + 1, $budget, $memo, true), joined: 1);
            }

            // كلمةٌ من المصحف سقطت من الاقتباس — داخل الموضع وبحدّ.
            if ($started && $budget > 0) {
                $options[] = self::then(self::walk($quote, $source, $i, $j + 1, $budget - 1, $memo, true), dropped: $source[$j]);
            }
        }

        // تكرارُ الكلمة السابقة.
        if ($started && $i > 0 && $quote[$i] === $quote[$i - 1]) {
            $options[] = self::then(self::walk($quote, $source, $i + 1, $j, $budget, $memo, true), repeated: 1);
        }

        $best = null;

        foreach ($options as $option) {
            if ($option !== null && ($best === null || $option['cost'] < $best['cost'])) {
                $best = $option;
            }
        }

        return $memo[$key] = $best;
    }

    /**
     * @param  array{cost: int, end: int, dropped: list<string>, joined: int, repeated: int}|null  $rest
     * @return array{cost: int, end: int, dropped: list<string>, joined: int, repeated: int}|null
     */
    private static function then(?array $rest, ?string $dropped = null, int $joined = 0, int $repeated = 0): ?array
    {
        if ($rest === null) {
            return null;
        }

        return [
            'cost' => $rest['cost'] + ($dropped === null ? 0 : 1),
            'end' => $rest['end'],
            'dropped' => $dropped === null ? $rest['dropped'] : [$dropped, ...$rest['dropped']],
            'joined' => $rest['joined'] + $joined,
            'repeated' => $rest['repeated'] + $repeated,
        ];
    }
}
