<?php

declare(strict_types=1);

use Bench\Catalog;
use Bench\PromptPack;

require_once __DIR__.'/../../bench/lib/Catalog.php';
require_once __DIR__.'/../../bench/lib/PromptPack.php';

/**
 * القياسُ يقرأ التعليمات **بحرفها** — CLAUDE.md §2 القاعدة الثانية.
 *
 * ومن أعاد صياغتها قاس صياغتَه هو لا النموذج. فهذا الاختبار يمنع أن
 * ينكسر القارئ صامتاً فيقيس بتعليمةٍ فارغة أو ناقصة.
 */
function benchPack(): PromptPack
{
    return PromptPack::fromFile(__DIR__.'/../../prompts/islamic/PROMPT-PACK.md');
}

it('يقرأ تعليمة كل مرحلة من الحزمة نفسها', function (): void {
    expect(benchPack()->stage(2))->toContain('استخرج بنيتها كما قصدها المتكلّم')
        // القاعدة الحاكمة في المرحلة الثالثة — عليها يقوم القياس كلّه.
        ->and(benchPack()->stage(3))->toContain('انقل لفظ الشاهد كما ورد في التفريغ حرفاً بحرف')
        ->and(benchPack()->stage(5))->toContain('الشواهد مثبَّتة');
});

it('يقف عند مرحلة لا وجود لها بدل أن يقيس بتعليمة فارغة', function (): void {
    expect(fn () => benchPack()->stage(9))->toThrow(RuntimeException::class);
});

// **معيار قبول T-00**: أربعة نماذج على الأقلّ من ثلاثة مزوّدين مختلفين.
it('يبقي القائمة القصيرة أربعةً من ثلاثة مزوّدين', function (): void {
    $shortlist = Catalog::shortlist();
    $providers = array_unique(array_map(
        static fn (string $key): string => Catalog::get($key)['provider'],
        $shortlist,
    ));

    expect($shortlist)->toHaveCount(4)
        ->and($providers)->toHaveCount(3);
});

// والكلفة من أسعار الجدول لا من ردّ المزوّد — كما في المنتج تماماً.
it('يحسب الكلفة من الأسعار المتحقَّقة', function (): void {
    // مليون دخل بـ4$ ومليون خرج بـ20$ على Opus 5.5.
    expect(Catalog::cost('opus-5.5', 1_000_000, 1_000_000))->toBe(24.0);
});
