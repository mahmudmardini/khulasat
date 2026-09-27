<?php

declare(strict_types=1);

use App\Enums\HadithBook;
use App\Enums\HadithGrade;
use App\Support\Hadith\Takhrij;

it('names the book a narration came from', function (HadithBook $book, string $expected): void {
    expect(Takhrij::forBook($book))->toBe($expected);
})->with([
    [HadithBook::Bukhari, 'رواه البخاري'],
    [HadithBook::Muslim, 'رواه مسلم'],
    [HadithBook::AbuDawud, 'رواه أبو داود'],
    [HadithBook::Tirmidhi, 'رواه الترمذي'],
    [HadithBook::Nasai, 'رواه النسائي'],
    [HadithBook::IbnMajah, 'رواه ابن ماجه'],
    [HadithBook::Malik, 'رواه مالك'],
]);

// ── ★ «متّفقٌ عليه» تُحسب ولا تُنقل ★ ───────────────────────────

it('calls a narration agreed upon when both Sahihs carry it', function (): void {
    expect(Takhrij::forBooks([HadithBook::Bukhari, HadithBook::Muslim]))
        ->toBe('متّفقٌ عليه: رواه البخاري ومسلم');
});

it('says it whatever the order the books matched in', function (): void {
    expect(Takhrij::forBooks([HadithBook::Muslim, HadithBook::Bukhari]))
        ->toBe('متّفقٌ عليه: رواه البخاري ومسلم');
});

it('keeps the agreement as the whole story when other books share it', function (): void {
    // الاتّفاق أعلى ما يُقال في تخريج حديث، فذكرُ من دون الشيخين بعده فضول.
    expect(Takhrij::forBooks([HadithBook::Tirmidhi, HadithBook::Bukhari, HadithBook::Muslim]))
        ->toBe('متّفقٌ عليه: رواه البخاري ومسلم');
});

it('never says agreed upon for one Sahih alone', function (): void {
    // إخراج أحدهما صحّة، واتّفاقُهما خبرٌ آخر. ولا يُقال الثاني بالأوّل.
    expect(Takhrij::forBooks([HadithBook::Bukhari]))->toBe('رواه البخاري')
        ->and(Takhrij::forBooks([HadithBook::Muslim]))->toBe('رواه مسلم');
});

// ── العطف بالعربية ──────────────────────────────────────────────

it('joins several books with the Arabic conjunction, not a comma', function (): void {
    expect(Takhrij::forBooks([HadithBook::AbuDawud, HadithBook::Tirmidhi]))
        ->toBe('رواه أبو داود والترمذي')
        ->and(Takhrij::forBooks([HadithBook::AbuDawud, HadithBook::Tirmidhi, HadithBook::Nasai]))
        ->toBe('رواه أبو داود والترمذي والنسائي');
});

it('orders the books by the index, not by how they matched', function (): void {
    // فلا يتبدّل نصّ التخريج بتبدّل ترتيب صفوف قاعدة البيانات.
    expect(Takhrij::forBooks([HadithBook::Nasai, HadithBook::AbuDawud]))
        ->toBe(Takhrij::forBooks([HadithBook::AbuDawud, HadithBook::Nasai]));
});

it('names a book once however many of its rows matched', function (): void {
    expect(Takhrij::forBooks([HadithBook::Bukhari, HadithBook::Bukhari]))->toBe('رواه البخاري');
});

it('says nothing when nothing matched', function (): void {
    expect(Takhrij::forBooks([]))->toBe('');
});

// ── ★ التخريج المصاحب — سياسة البيان §7-5 ★ ────────────────────

it('lets the two Sahihs speak for themselves', function (): void {
    // **إخراج الشيخين درجةٌ منصوصة** — §7-3، وتنبيه §7-5 البند ٢. فلا
    // تُضاف إليه جملةُ تصحيح، إذ الإخراج هو الحكم.
    expect(Takhrij::disclosure([HadithBook::Bukhari, HadithBook::Muslim], HadithGrade::Sahih))
        ->toBe('متّفقٌ عليه: رواه البخاري ومسلم')
        ->and(Takhrij::disclosure([HadithBook::Bukhari], HadithGrade::Sahih))
        ->toBe('رواه البخاري');
});

it('states the soundness of a hadith the Sahihs do not carry', function (): void {
    // «رواه الترمذي» وحدها لا تقول شيئاً عن درجته، بخلاف «رواه البخاري».
    expect(Takhrij::disclosure([HadithBook::Tirmidhi], HadithGrade::Sahih))
        ->toBe('رواه الترمذي — وهو صحيح')
        ->and(Takhrij::disclosure([HadithBook::Tirmidhi], HadithGrade::Hasan))
        ->toBe('رواه الترمذي — وهو حسن');
});

it('discloses weakness rather than hiding it', function (): void {
    // ★ **وهذا هو المسوّغ كلّه.** جاز نشر الضعيف لأنّه يُنشر مقروناً
    //   ببيانه، والآفة في نقله موهِماً صحّته لا في نقله مبيَّناً.
    expect(Takhrij::disclosure([HadithBook::AbuDawud], HadithGrade::Daif))
        ->toBe('رواه أبو داود — وإسناده ضعيف');
});

it('says plainly that a fabricated narration does not stand', function (): void {
    expect(Takhrij::disclosure([HadithBook::IbnMajah], HadithGrade::Mawdu))
        ->toBe('رواه ابن ماجه — ولا يصحّ، حكم عليه أهل العلم بالوضع');
});

it('gives no sentence at all to what has no stated grade', function (): void {
    // **وما جُهل مصدره لا يُنشر**، فلا جملة له. وعجزُنا عن التخريج ليس
    // حكماً بالوضع، فلا يُوصَف بشيء.
    expect(Takhrij::disclosure([HadithBook::Bukhari], HadithGrade::Unknown))->toBe('')
        ->and(Takhrij::disclosure([], HadithGrade::Sahih))->toBe('');
});

it('puts the number inside the attribution, not after the ruling', function (): void {
    // «رواه ابن ماجه — وإسناده ضعيف، رقم ٢٢٤» تُوهم أنّ الرقم رقمُ الحكم.
    expect(Takhrij::disclosure([HadithBook::IbnMajah], HadithGrade::Daif, 'رقم ٢٢٤'))
        ->toBe('رواه ابن ماجه، رقم ٢٢٤ — وإسناده ضعيف');
});
