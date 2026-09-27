<?php

declare(strict_types=1);

namespace App\Support\Hadith;

use App\Support\Arabic;

/**
 * الكلماتُ بمواضعها من النصّ المشكَّل ومعها صورتُها المطبَّعة — T-116.
 *
 * **والمواضعُ من المشكَّل لا من المطبَّع**: التطبيع ينزع التشكيل فتختلّ
 * المواضع، والقطعُ يجب أن يقع على النصّ كما يُنشر. فتُطبَّع كلُّ كلمةٍ وحدها
 * ويبقى موضعُها من الأصل معلوماً.
 *
 * **وهي واحدةٌ لقارئَيها** — {@see MatnExtractor} و{@see MatnOpening}: لو
 * قسَمَ كلٌّ منهما الكلامَ بطريقته لاختلف عندهما حدُّ الكلمة، فقطع أحدُهما
 * حيث لا يقطع الآخر — وهو عينُ ما تحذّر منه {@see Arabic} في التطبيع.
 */
final class Words
{
    private function __construct() {}

    /**
     * @return list<array{text: string, norm: string, at: int}>
     */
    public static function of(string $text): array
    {
        $parts = preg_split('/\s+/u', $text, -1, PREG_SPLIT_NO_EMPTY | PREG_SPLIT_OFFSET_CAPTURE) ?: [];
        $words = [];

        foreach ($parts as [$word, $at]) {
            $norm = Arabic::normalize($word);

            // وما طُبّع إلى فراغ — «ـ» وعلاماتُ الترقيم — فاصلٌ لا كلمة،
            // فيُسقَط من العدّ حتى تتجاور الكلماتُ كما تُقرأ.
            if ($norm === '') {
                continue;
            }

            $words[] = ['text' => $word, 'norm' => $norm, 'at' => $at];
        }

        return $words;
    }

    /**
     * أتطابق الكلماتُ من هذا الموضع تتابعاً مطبَّعاً بعينه؟
     *
     * @param  list<array{text: string, norm: string, at: int}>  $words
     * @param  list<string>  $sequence
     */
    public static function match(array $words, int $index, array $sequence): bool
    {
        foreach ($sequence as $offset => $expected) {
            if (($words[$index + $offset]['norm'] ?? null) !== $expected) {
                return false;
            }
        }

        return true;
    }
}
