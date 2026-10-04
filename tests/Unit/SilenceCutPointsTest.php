<?php

declare(strict_types=1);

use App\Support\Transcript\SilenceCutPoints;

// المواصفة §5-أ-4: «عند الصمت لا عند زمن ثابت، حتى لا تُقطع الكلمة».

/** @return list<array{start: float, end: float}> */
function silencesAt(array ...$pairs): array
{
    return array_map(
        static fn (array $pair): array => ['start' => (float) $pair[0], 'end' => (float) $pair[1]],
        $pairs,
    );
}

it('does not cut a recording shorter than one chunk', function (): void {
    expect(SilenceCutPoints::cuts(400.0, silencesAt([100, 101]), 600))->toBe([])
        ->and(SilenceCutPoints::segments(400.0, [], 600))
        ->toBe([['start' => 0.0, 'end' => 400.0]]);
});

// **أهمّ اختبار هنا.** لولا هذا لوقع القطع عند ٦٠٠ بالضبط، وهي داخل الكلام.
it('moves the cut to the silence nearest the target', function (): void {
    // سكتة عند ٥٨٠–٥٨٤، ووسطها ٥٨٢.
    $cuts = SilenceCutPoints::cuts(1_000.0, silencesAt([580, 584]), 600);

    expect($cuts)->toBe([582.0]);
});

it('cuts in the middle of the silence, not at either edge', function (): void {
    // القطع عند أوّلها يقصّ ذيل النَفَس، وعند آخرها يبتلع بداية ما بعدها.
    expect(SilenceCutPoints::cuts(1_000.0, silencesAt([590, 600]), 600))->toBe([595.0]);
});

it('prefers the closest silence when several sit in the window', function (): void {
    $cuts = SilenceCutPoints::cuts(1_000.0, silencesAt([520, 522], [596, 598], [660, 662]), 600);

    expect($cuts)->toBe([597.0]);
});

// لا سكتة قريبة: يُقطع عند الهدف. ومقطعٌ مقطوعُ الكلمة خيرٌ من مقطعٍ
// يتجاوز حدّ المزوّد فيُرفض كلّه.
it('falls back to the exact target when no silence is near', function (): void {
    expect(SilenceCutPoints::cuts(1_000.0, silencesAt([100, 102]), 600))->toBe([600.0]);
});

// **ولا قطعَ أعمى ما دامت سكتةٌ في المتناول**: خارج النافذة القريبة يُمدّ البحث
// إلى ربع ساعة قبل أن يُقطع عند الهدف. مقطعٌ أقصر أو أطول خيرٌ من كلمةٍ مشطورة.
it('reaches beyond the near window before cutting blind', function (): void {
    // النافذة القريبة ٢٠٪ من ٦٠٠ = ١٢٠ ثانية، فـ ٤٥٠ خارجها (المسافة ١٥٠) —
    // لكنّها في متناول المدّ، فيُقطع عندها لا عند ٦٠٠.
    expect(SilenceCutPoints::cuts(1_000.0, silencesAt([449, 451]), 600))->toBe([450.0])
        ->and(SilenceCutPoints::blindCuts(1_000.0, silencesAt([449, 451]), 600))->toBe(0);

    // و٤٩٠ داخل النافذة القريبة (المسافة ١١٠).
    expect(SilenceCutPoints::cuts(1_000.0, silencesAt([489, 491]), 600))->toBe([490.0]);
});

// قاعةٌ فيها مروحة أو صدى: لا سكتةَ صريحة قرب الهدف، لكن بين الجملتين نَفَس.
it('takes a soft pause near the target before stretching to a far silence', function (): void {
    $cuts = SilenceCutPoints::cuts(1_000.0, silencesAt([449, 451]), 600, soft: silencesAt([655, 657]));

    expect($cuts)->toBe([656.0]);
});

it('prefers a clear silence to a soft pause in the same window', function (): void {
    // اللّيّنة أقرب إلى الهدف (٦٠٠)، والصريحة أولى ولو بعدت قليلاً.
    $cuts = SilenceCutPoints::cuts(1_000.0, silencesAt([580, 584]), 600, soft: silencesAt([599, 601]));

    expect($cuts)->toBe([582.0]);
});

// ربعُ الساعة حدٌّ: أبعدُ منه يُخرج مقطعاً قد يتجاوز حدّ المزوّد.
it('stretches a chunk to a quarter hour at most, then cuts at the target and says so', function (): void {
    expect(SilenceCutPoints::cuts(2_000.0, silencesAt([880, 882]), 600))->toBe([881.0, 1_481.0])
        ->and(SilenceCutPoints::blindCuts(2_000.0, silencesAt([880, 882]), 600))->toBe(1);

    expect(SilenceCutPoints::cuts(2_000.0, silencesAt([930, 932]), 600)[0])->toBe(600.0)
        ->and(SilenceCutPoints::blindCuts(2_000.0, silencesAt([930, 932]), 600))->toBeGreaterThan(0);
});

// سكتةٌ باكرة جداً تُخرج مقطعاً ثوانيَ، وهي في أوّل الدرس كثيرة.
it('refuses a silence that would make a stub segment', function (): void {
    // أقصر مقطع ٢٥٪ من ٦٠٠ = ١٥٠ ثانية. وسكتة عند ٢٠ لا تصلح نقطةَ قطع.
    expect(SilenceCutPoints::cuts(1_000.0, silencesAt([19, 21]), 600))->toBe([600.0]);
});

it('walks the whole recording, cutting at each silence it finds', function (): void {
    $cuts = SilenceCutPoints::cuts(
        2_000.0,
        silencesAt([595, 605], [1_190, 1_200], [1_790, 1_800]),
        600,
    );

    expect($cuts)->toBe([600.0, 1_195.0, 1_795.0]);
});

it('turns the cuts into ordered segments that cover the whole recording', function (): void {
    $segments = SilenceCutPoints::segments(1_500.0, silencesAt([598, 602], [1_198, 1_202]), 600);

    expect($segments)->toBe([
        ['start' => 0.0, 'end' => 600.0],
        ['start' => 600.0, 'end' => 1_200.0],
        ['start' => 1_200.0, 'end' => 1_500.0],
    ]);

    // ولا فجوة ولا تداخل: نهايةُ كلٍّ بدايةُ ما بعده، وآخرُها نهاية التسجيل.
    $covered = 0.0;

    foreach ($segments as $segment) {
        expect($segment['start'])->toBe($covered);
        $covered = $segment['end'];
    }

    expect($covered)->toBe(1_500.0);
});

it('handles an empty or zero-length recording', function (): void {
    expect(SilenceCutPoints::segments(0.0, [], 600))->toBe([])
        ->and(SilenceCutPoints::cuts(0.0, [], 600))->toBe([]);
});

// حارسٌ ضدّ الدوران اللانهائي: لو أعاد الاختيار نقطةً لا تتقدّم.
it('always terminates, whatever the silences say', function (): void {
    $cuts = SilenceCutPoints::cuts(10_000.0, silencesAt([0, 0], [1, 1]), 600);

    expect(count($cuts))->toBeLessThan(50)
        ->and($cuts)->toBe(array_values(array_unique($cuts)));

    // والنقاط متزايدة تماماً.
    $previous = 0.0;

    foreach ($cuts as $cut) {
        expect($cut)->toBeGreaterThan($previous);
        $previous = $cut;
    }
});

// **موازنةُ الذيل.** ١٢١٨ ثانية لو قُطعت ٦٠٠+٦٠٠ لخرج ذيلٌ من ١٨ ثانية،
// وهو نداءُ تفريغٍ كامل على جملةٍ ناقصة. فيُقسم الباقي نصفين.
it('splits the tail evenly instead of leaving a stub chunk', function (): void {
    $segments = SilenceCutPoints::segments(1_218.0, [], 600);

    $lengths = array_map(
        static fn (array $segment): float => $segment['end'] - $segment['start'],
        $segments,
    );

    expect($segments)->toHaveCount(3)
        ->and($lengths[0])->toBe(600.0)
        ->and($lengths[1])->toBe(309.0)
        ->and($lengths[2])->toBe(309.0);

    // ولا مقطع يتجاوز الحدّ، فالحدّ هو سبب التقطيع أصلاً.
    foreach ($lengths as $length) {
        expect($length)->toBeLessThanOrEqual(600.0);
    }
});
