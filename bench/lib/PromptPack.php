<?php

declare(strict_types=1);

namespace Bench;

use RuntimeException;

/**
 * Read the six stage prompts from the pack, verbatim.
 *
 * **بحرفها لا بمعناها** — CLAUDE.md §2 القاعدة الثانية: «لا تكتب تعليمات
 * النماذج من عندك». والقياسُ الذي يعيد صياغة التعليمة يقيس صياغتَه هو،
 * لا النموذج. فتُقرأ من الملفّ نفسه الذي يقرؤه المنتج.
 *
 * وشكلُ الحزمة ثابت: عنوانٌ `## المرحلة N — …` ثمّ أوّل كتلة سياج بعده.
 */
final class PromptPack
{
    public function __construct(private readonly string $markdown) {}

    public static function fromFile(string $path): self
    {
        $contents = @file_get_contents($path);

        if ($contents === false) {
            throw new RuntimeException("تعذّرت قراءة حزمة التعليمات: {$path}");
        }

        return new self($contents);
    }

    /** تعليمةُ المرحلة كما هي، بلا أسوار الكود. */
    public function stage(int $number): string
    {
        $sections = preg_split('/^## /mu', $this->markdown) ?: [];

        foreach ($sections as $section) {
            if (! preg_match('/^المرحلة\s+'.$number.'\s+—/u', $section)) {
                continue;
            }

            if (preg_match('/```\s*\n(.*?)\n```/su', $section, $matches) !== 1) {
                throw new RuntimeException("المرحلة {$number} بلا كتلة تعليمات في الحزمة.");
            }

            return trim($matches[1]);
        }

        throw new RuntimeException("لا مرحلة برقم {$number} في الحزمة.");
    }
}
