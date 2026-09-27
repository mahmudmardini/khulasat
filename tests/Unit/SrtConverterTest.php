<?php

declare(strict_types=1);

use App\Support\Transcript\SrtConverter;

// المواصفة §5-أ-3: التداخل هو فخّ هذه المرحلة. والاختبار يجري على ملفّ
// ترجمة آلية حقيقي متداخل لا على نصّ مصنوع، لأنّ الفخّ في شكل الملفّ نفسه.

function fixtureSrt(string $name): string
{
    return (string) file_get_contents(__DIR__.'/../Fixtures/transcript/'.$name);
}

it('halves nothing when the captions do not overlap', function (): void {
    $srt = <<<'SRT'
        1
        00:00:01,000 --> 00:00:03,000
        قال المؤلف رحمه الله

        2
        00:00:03,000 --> 00:00:05,000
        باب فضل العلم وأهله
        SRT;

    expect(SrtConverter::toText($srt))
        ->toBe('قال المؤلف رحمه الله باب فضل العلم وأهله');
});

it('drops a block that exactly repeats the one before it', function (): void {
    $srt = <<<'SRT'
        1
        00:00:01,000 --> 00:00:03,000
        وصلى الله على نبينا محمد

        2
        00:00:03,000 --> 00:00:03,040
        وصلى الله على نبينا محمد
        SRT;

    expect(SrtConverter::toText($srt))->toBe('وصلى الله على نبينا محمد');
});

// المواصفة §5-أ-3 الخطوة ٢: **تُقارَن الكلمات لا الأحرف.** ولو قُورنت
// الأحرف لقُطعت الكلمة في منتصفها، فخرج نصفُ كلمة نصّاً.
it('compares words and not characters', function (): void {
    $srt = <<<'SRT'
        1
        00:00:01,000 --> 00:00:03,000
        في الحديث الصحيح

        2
        00:00:03,000 --> 00:00:05,000
        الصحيحين رواية أخرى
        SRT;

    // «الصحيح» ليست «الصحيحين»، فلا يُحذف منها شيء.
    expect(SrtConverter::toText($srt))
        ->toBe('في الحديث الصحيح الصحيحين رواية أخرى');
});

// التداخل يُقارَن على المطبَّع: المفرِّغ يُشكّل كلمةً في كتلة ويتركها في
// التي بعدها، والتكرار تكرارٌ وإن اختلف تشكيله — انظر Arabic::normalize.
it('detects an overlap whose diacritics differ between blocks', function (): void {
    $srt = <<<'SRT'
        1
        00:00:01,000 --> 00:00:03,000
        قال رسول الله

        2
        00:00:03,000 --> 00:00:05,000
        قَالَ رَسُولُ اللَّهِ صلى الله عليه وسلم
        SRT;

    expect(SrtConverter::toText($srt))
        ->toBe('قال رسول الله صلى الله عليه وسلم');
});

// والتشكيل يبقى في المخرَج: النصّ يُعرض على مراجع شرعي ويُقتبس منه،
// و«مَنْ» ليست «مِنْ». التطبيع للمقارنة وحدها.
it('keeps diacritics in the text it writes', function (): void {
    $srt = <<<'SRT'
        1
        00:00:01,000 --> 00:00:03,000
        مَنْ عَمِلَ صَالِحاً
        SRT;

    expect(SrtConverter::toText($srt))->toBe('مَنْ عَمِلَ صَالِحاً');
});

it('strips caption markup and inline timestamps', function (): void {
    $srt = <<<'SRT'
        1
        00:00:01,000 --> 00:00:03,000
        <c.colorE5E5E5>باب</c> <00:00:02.100><i>الإخلاص</i> {\an8}والنية
        SRT;

    expect(SrtConverter::toText($srt))->toBe('باب الإخلاص والنية');
});

// وتُستبدل الوسوم بمسافة لا تُحذف: لو حُذفت لالتصقت الكلمتان حول الوسم
// فصارتا كلمةً لا وجود لها في العربية.
it('does not fuse the words a tag sat between', function (): void {
    $srt = <<<'SRT'
        1
        00:00:01,000 --> 00:00:03,000
        الحمد<c> </c>لله
        SRT;

    expect(SrtConverter::toText($srt))->toBe('الحمد لله');
});

it('starts a new paragraph after a gap longer than two seconds', function (): void {
    $srt = <<<'SRT'
        1
        00:00:01,000 --> 00:00:03,000
        هذا الفصل الأول

        2
        00:00:05,001 --> 00:00:07,000
        وهذا فصل جديد
        SRT;

    expect(SrtConverter::toText($srt))->toBe("هذا الفصل الأول\n\nوهذا فصل جديد");
});

it('keeps one paragraph when the gap is exactly two seconds', function (): void {
    $srt = <<<'SRT'
        1
        00:00:01,000 --> 00:00:03,000
        هذا الفصل الأول

        2
        00:00:05,000 --> 00:00:07,000
        وتمامه هنا
        SRT;

    expect(SrtConverter::toText($srt))->toBe('هذا الفصل الأول وتمامه هنا');
});

// الفجوة تُقاس بالتوقيت وإن حُذف نصّ الكتلة السابقة في إزالة التداخل:
// الصمت واقعٌ في الصوت لا في النصّ.
it('measures the gap across a block whose text was removed as duplicate', function (): void {
    $srt = <<<'SRT'
        1
        00:00:01,000 --> 00:00:03,000
        خاتمة الفصل الأول

        2
        00:00:03,000 --> 00:00:03,050
        خاتمة الفصل الأول

        3
        00:00:16,000 --> 00:00:18,000
        عنوان جديد
        SRT;

    expect(SrtConverter::toText($srt))->toBe("خاتمة الفصل الأول\n\nعنوان جديد");
});

// المواصفة §5-أ-3 الخطوة ٤: فقرة جديدة «عند بلوغ ٤٠٠ كلمة». والفاصل يقع
// عند حدّ الكتلة لا في وسطها: الكتلة جملةٌ منطوقة، وقطعُها نصفين ليوافق
// العدّاد يُخرج نصف جملة فقرةً. فالفقرة تتجاوز الحدّ بكتلة واحدة على الأكثر.
it('breaks a paragraph at four hundred words, on the cue boundary', function (): void {
    $blocks = [];
    $wordsPerCue = 3;

    // ٢٠٠ كتلة × ٣ كلمات = ٦٠٠ كلمة، بتوقيت متّصل فلا فجوة تفصل.
    for ($i = 0; $i < 200; $i++) {
        $start = sprintf('00:%02d:%02d,000', intdiv($i, 60), $i % 60);
        $end = sprintf('00:%02d:%02d,000', intdiv($i + 1, 60), ($i + 1) % 60);
        $blocks[] = ($i + 1)."\n{$start} --> {$end}\nكلمة رقم {$i}";
    }

    $paragraphs = explode("\n\n", SrtConverter::toText(implode("\n\n", $blocks)));

    $counts = array_map(
        static fn (string $paragraph): int => count(preg_split('/\s+/u', $paragraph) ?: []),
        $paragraphs,
    );

    expect($paragraphs)->toHaveCount(2)
        ->and($counts[0])->toBeGreaterThanOrEqual(SrtConverter::PARAGRAPH_MAX_WORDS)
        ->and($counts[0])->toBeLessThan(SrtConverter::PARAGRAPH_MAX_WORDS + $wordsPerCue)
        // ولا تُفقد كلمة في التقسيم.
        ->and(array_sum($counts))->toBe(200 * $wordsPerCue);
});

it('reads a file that uses CRLF line endings and a byte order mark', function (): void {
    $srt = "\u{FEFF}1\r\n00:00:01,000 --> 00:00:03,000\r\nباب الطهارة\r\n";

    expect(SrtConverter::toText($srt))->toBe('باب الطهارة');
});

it('reads VTT timings that use a dot and carry no block numbers', function (): void {
    $srt = <<<'SRT'
        00:00:01.000 --> 00:00:03.000 align:start position:0%
        باب النية

        00:00:03.000 --> 00:00:05.000
        وأثرها في العمل
        SRT;

    expect(SrtConverter::toText($srt))->toBe('باب النية وأثرها في العمل');
});

// كتلة بلا توقيت مقروء تُترك ولا تُخمَّن: إدخال نصٍّ لا نعرف موضعه من
// الدرس أسوأ من إسقاطه، لأنّه يُقرأ كأنّه في سياق غيره.
it('skips a block whose timing line cannot be read', function (): void {
    $srt = <<<'SRT'
        1
        هذا سطر بلا توقيت

        2
        00:00:03,000 --> 00:00:05,000
        وهذا نصّ موقوت
        SRT;

    expect(SrtConverter::toText($srt))->toBe('وهذا نصّ موقوت');
});

it('returns an empty string for a file with no readable cues', function (): void {
    expect(SrtConverter::toText(''))->toBe('')
        ->and(SrtConverter::toText("\n\n  \n"))->toBe('');
});

it('reads timings past the first hour', function (): void {
    $srt = <<<'SRT'
        1
        01:00:01,000 --> 01:00:03,000
        الجزء الأخير

        2
        01:00:03,000 --> 01:00:05,000
        من الدرس
        SRT;

    expect(SrtConverter::toText($srt))->toBe('الجزء الأخير من الدرس');
});
