<?php

declare(strict_types=1);

use App\Actions\Stages\GuardQuotedText;
use App\Enums\MatchStatus;
use App\Enums\ReviewStatus;
use App\Models\EvidenceItem;
use App\Models\SummaryJob;
use App\Models\Tenant;
use Database\Seeders\QuranTestSeeder;
use Illuminate\Support\Facades\Log;

/*
 * T-160: حارسُ الاقتباس خارج كتل الشاهد — CLAUDE.md §2 الحدّ الثالث.
 *
 * نموذجُ الكتابة يرى التفريغ كاملاً، فإن نقل حديثاً داخل فقرةٍ عادية مرّ بلا
 * تحقّق. **وقرار مالك المنتج (٢٥ أيلول ٢٠٢٦): تُحذف الجملة وحدها.**
 */

const SETTLED_HADITH = 'إنما الأعمال بالنيات وإنما لكل امرئ ما نوى';

beforeEach(function (): void {
    $this->seed(QuranTestSeeder::class);

    $this->tenant = Tenant::factory()->create();
    $this->job = SummaryJob::factory()->create(['tenant_id' => $this->tenant->id]);

    EvidenceItem::factory()->create([
        'summary_job_id' => $this->job->id,
        'tenant_id' => $this->tenant->id,
        'kind' => 'hadith',
        'raw_text' => SETTLED_HADITH,
        'matched_text' => SETTLED_HADITH,
        'match_status' => MatchStatus::Exact,
        'review_status' => ReviewStatus::AutoPassed,
    ]);
});

/** @param  list<array<string, mixed>>  $blocks */
function quotedBody(array $blocks, string $closing = 'خاتمة'): array
{
    return ['sections' => [['heading' => 'عنوان', 'blocks' => $blocks]], 'closing' => $closing];
}

function guardQuotes(SummaryJob $job, array $body): array
{
    return app(GuardQuotedText::class)->handle($job, $body);
}

it('drops only the sentence that quotes an unverified hadith, and logs it', function (): void {
    Log::spy();

    $guarded = guardQuotes($this->job, quotedBody([[
        'type' => 'paragraph',
        'text' => 'الرحمة أصل المعاملة. قال رسول الله ﷺ: «من لا يرحم الناس لا يرحمه الله». فمن رحم رُحم.',
    ]]));

    expect($guarded['sections'][0]['blocks'][0]['text'])->toBe('الرحمة أصل المعاملة. فمن رحم رُحم.');

    Log::shouldHaveReceived('warning')->withArgs(
        fn (string $message, array $context): bool => $message === 'quoted_text_guard.dropped'
            && $context['summary_job_id'] === $this->job->id
            && count($context['sentences']) === 1,
    );
});

it('keeps a quote that is part of a settled evidence', function (): void {
    $text = 'وأصل ذلك قوله ﷺ: «إنما الأعمال بالنيات». فالنية تسبق العمل.';

    $guarded = guardQuotes($this->job, quotedBody([['type' => 'paragraph', 'text' => $text]]));

    expect($guarded['sections'][0]['blocks'][0]['text'])->toBe($text);
});

it('drops a quote that adds unverified words to a settled evidence', function (): void {
    $guarded = guardQuotes($this->job, quotedBody([[
        'type' => 'paragraph',
        'text' => 'قال النبي ﷺ: «إنما الأعمال بالنيات والخواتيم». والنية تُصلح العمل.',
    ]]));

    expect($guarded['sections'][0]['blocks'][0]['text'])->toBe('والنية تُصلح العمل.');
});

it('keeps an ayah in ornate brackets when it matches the mushaf, and drops it when it does not', function (): void {
    $guarded = guardQuotes($this->job, quotedBody([
        ['type' => 'paragraph', 'text' => 'قال تعالى: ﴿إِنَّ الْإِنسَانَ خُلِقَ هَلُوعًا﴾ ۝١٩. وهذا وصفٌ للطبع.'],
        ['type' => 'paragraph', 'text' => 'قال تعالى: ﴿إن الإنسان خلق جزوعا﴾. وهذا وصفٌ للطبع.'],
    ]));

    expect($guarded['sections'][0]['blocks'][0]['text'])
        ->toBe('قال تعالى: ﴿إِنَّ الْإِنسَانَ خُلِقَ هَلُوعًا﴾ ۝١٩. وهذا وصفٌ للطبع.')
        ->and($guarded['sections'][0]['blocks'][1]['text'])->toBe('وهذا وصفٌ للطبع.');
});

it('drops an unbracketed saying after a colon', function (): void {
    $guarded = guardQuotes($this->job, quotedBody([[
        'type' => 'paragraph',
        'text' => 'قال رسول الله ﷺ: الدين النصيحة لله ولرسوله. فالنصيحة باب الدين.',
    ]]));

    expect($guarded['sections'][0]['blocks'][0]['text'])->toBe('فالنصيحة باب الدين.');
});

it('leaves meaning-only prose and unattributed quotation marks untouched', function (): void {
    $prose = 'بيّن النبي ﷺ أنّ الرحمة سببٌ لرحمة الله. وسمّى الشيخ درسه «عنوان الدرس في القلب».';
    $list = 'المحاور ثلاثة: النية والعمل والثبات.';
    $sheikh = 'قال الشيخ في شرح الحديث: النية أساس العمل كله.';

    $guarded = guardQuotes($this->job, quotedBody([
        ['type' => 'paragraph', 'text' => $prose],
        ['type' => 'lead', 'text' => $list],
        ['type' => 'paragraph', 'text' => $sheikh],
    ]));

    expect($guarded['sections'][0]['blocks'][0]['text'])->toBe($prose)
        ->and($guarded['sections'][0]['blocks'][1]['text'])->toBe($list)
        ->and($guarded['sections'][0]['blocks'][2]['text'])->toBe($sheikh);
});

it('does not split a sentence on a full stop inside the quote', function (): void {
    $guarded = guardQuotes($this->job, quotedBody([[
        'type' => 'paragraph',
        'text' => 'قال النبي ﷺ: «اتق الله حيثما كنت. وأتبع السيئة الحسنة تمحها». والعمل بعده.',
    ]]));

    expect($guarded['sections'][0]['blocks'][0]['text'])->toBe('والعمل بعده.');
});

it('guards nested text, drops a block left empty, guards the closing, and never touches evidence blocks', function (): void {
    $evidence = ['type' => 'evidence', 'kind' => 'hadith', 'text' => SETTLED_HADITH, 'source' => 'متّفق عليه'];

    $guarded = guardQuotes($this->job, quotedBody([
        ['type' => 'paragraph', 'text' => 'قال النبي ﷺ: «من لا يرحم الناس لا يرحمه الله».'],
        ['type' => 'cards', 'items' => [
            ['title' => 'الرحمة', 'body' => 'قال ﷺ: «الراحمون يرحمهم الرحمن». والرحمة خلق.', 'icon' => 'heart'],
        ]],
        $evidence,
    ], closing: 'والختام قوله ﷺ: «خير الناس أنفعهم للناس».'));

    $blocks = $guarded['sections'][0]['blocks'];

    expect($blocks)->toHaveCount(2)
        ->and($blocks[0]['items'][0]['body'])->toBe('والرحمة خلق.')
        ->and($blocks[0]['items'][0]['icon'])->toBe('heart')
        ->and($blocks[1])->toBe($evidence)
        ->and($guarded['closing'])->toBe('');
});
