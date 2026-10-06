<?php

declare(strict_types=1);

namespace App\Support\Render;

use App\Enums\SlideKind;
use App\Support\Arabic;

/** شريحةٌ واحدة من الكاروسيل — المواصفة §8-أ، والمرحلة ٧. */
final readonly class Slide
{
    public function __construct(
        public int $index,
        public SlideKind $kind,
        public string $heading,
        public string $body,
        public ?string $sourceLine = null,
        /**
         * أثُبِّت متنُها من شاهدٍ متحقَّق منه؟
         *
         * ولا معنى لها إلّا في شرائح اللفظ المصدريّ: `true` تعني أنّ ما
         * يُعرض هو لفظ المصحف أو لفظ كتاب الحديث، لا ما كتبه النموذج.
         */
        public bool $anchored = false,
    ) {}

    /**
     * عدد كلمات **النصّ الحرّ** في هذه الشريحة.
     *
     * **ولفظ المصدر لا يُعدّ**: سقف الأربعين كلمة قيدٌ على ما يكتبه النموذج
     * ليبقى الشريحة مقروءة، لا إذنٌ باختصار آية. وآيةٌ من خمسين كلمة تُعرض
     * كاملةً وتُفرد بشريحتها — المواصفة §8-أ.
     */
    public function freeWordCount(): int
    {
        $counted = $this->kind->carriesSourceWording()
            ? $this->heading
            : $this->heading.' '.$this->body;

        return self::words($counted);
    }

    /** رقم الشريحة كما يظهر عليها — عربيّ هنديّ (المرحلة ٧). */
    public function label(): string
    {
        return Arabic::toArabicIndicDigits($this->index);
    }

    public function withBody(string $body, ?string $sourceLine, bool $anchored): self
    {
        return new self($this->index, $this->kind, $this->heading, $body, $sourceLine, $anchored);
    }

    public function withIndex(int $index): self
    {
        return new self($index, $this->kind, $this->heading, $this->body, $this->sourceLine, $this->anchored);
    }

    /** @return array<string, mixed> الصفّ كما يُحفظ في `outputs.meta` ويُنسخ يدوياً. */
    public function toArray(): array
    {
        return [
            'index' => $this->index,
            'kind' => $this->kind->value,
            'heading' => $this->heading,
            'body' => $this->body,
            'source_line' => $this->sourceLine,
            'anchored' => $this->anchored,
        ];
    }

    /**
     * **وما لا حرفَ فيه ولا رقم لا يُعدّ كلمة** — T-217: «—» و«:» و«…» بين
     * مسافتين كانت تُحسب في سقف الأربعين، فتُرفض شريحةٌ بثمانٍ وثلاثين كلمةً
     * وشرطتين، ولا يدري أحدٌ أيُّ كلمةٍ زادت.
     */
    private static function words(string $text): int
    {
        $tokens = preg_split('/\s+/u', $text, -1, PREG_SPLIT_NO_EMPTY) ?: [];

        return count(array_filter(
            $tokens,
            static fn (string $token): bool => preg_match('/[\p{L}\p{N}]/u', $token) === 1,
        ));
    }
}
