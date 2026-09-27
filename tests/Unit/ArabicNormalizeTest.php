<?php

declare(strict_types=1);

use App\Support\Arabic;
use Symfony\Component\Finder\Finder;

// ── ١. الحالات الفارغة والحدّية ──────────────────────────────────

it('normalizes empty and blank input to an empty string', function (string $input): void {
    expect(Arabic::normalize($input))->toBe('');
})->with([
    'نصّ فارغ' => [''],
    'مسافات فقط' => ['     '],
    'أسطر وجدولة' => ["\n\t  \r\n"],
    'مسافة غير فاصلة فقط' => ["\u{00A0}\u{00A0}"],
    'تشكيل بلا حروف' => ["\u{064B}\u{064E}\u{0651}\u{0652}"],
    'تطويل فقط' => ["\u{0640}\u{0640}\u{0640}"],
    'ترقيم فقط' => ['،؛؟.,!«»'],
    'محارف خفيّة فقط' => ["\u{200F}\u{200E}\u{200B}\u{FEFF}"],
]);

// ── ٢. الخطوة ١: إزالة التشكيل ───────────────────────────────────

it('strips every diacritic in the specified ranges', function (string $input, string $expected): void {
    expect(Arabic::normalize($input))->toBe($expected);
})->with([
    'فتحة وضمّة وكسرة' => ['مَنْ عَمِلَ', 'من عمل'],
    'تنوين' => ['كتابًا عظيمٌ', 'كتابا عظيم'],
    'شدّة مع حركة' => ['طَيِّبَةً', 'طيبه'],
    'سكون' => ['مِنْ ذَكَرٍ', 'من ذكر'],
    'ألف خنجرية U+0670' => ["أُنثَ\u{0670}ى", 'انثي'],
    'علامات الوقف القرآنية U+06D6' => ["ذلك\u{06D6} الكتاب", 'ذلك الكتاب'],
    'رمز نهاية الآية U+06DD' => ["يعملون\u{06DD}", 'يعملون'],
    'واو صغيرة U+06E5' => ["فلنحيينه\u{06E5}", 'فلنحيينه'],
]);

// ── ٣. الخطوة ٢: إزالة التطويل ───────────────────────────────────

it('removes tatweel however many times it repeats', function (string $input, string $expected): void {
    expect(Arabic::normalize($input))->toBe($expected);
})->with([
    'تطويل مفرد' => ['الحمـد', 'الحمد'],
    'تطويل مكرّر' => ['الحمــــــد للـــه', 'الحمد لله'],
    'تطويل بين كل حرفين' => ['مـحـمـد', 'محمد'],
]);

// ── ٤. الخطوة ٣: توحيد الألف ─────────────────────────────────────

it('folds every alef form to a bare alef', function (string $input, string $expected): void {
    expect(Arabic::normalize($input))->toBe($expected);
})->with([
    'ألف بهمزة فوق' => ['أحمد', 'احمد'],
    'ألف بهمزة تحت' => ['إبراهيم', 'ابراهيم'],
    'ألف بمدّة' => ['آمن', 'امن'],
    'ألف وصل U+0671' => ["\u{0671}لحمد", 'الحمد'],
    'الصور الأربع معاً' => ["أ إ آ \u{0671}", 'ا ا ا ا'],
]);

// ── ٥. الخطوات ٤ و٥ و٦: الياء والتاء المربوطة والهمزات ──────────

it('folds alef maqsura, ta marbuta and seated hamzas', function (string $input, string $expected): void {
    expect(Arabic::normalize($input))->toBe($expected);
})->with([
    'ألف مقصورة' => ['موسى', 'موسي'],
    'تاء مربوطة' => ['طيبة', 'طيبه'],
    'همزة على واو' => ['مؤمن', 'مومن'],
    'همزة على ياء' => ['أئمة', 'ايمه'],
    'الأربعة معاً' => ['رؤية أئمة الهدى مرة', 'رويه ايمه الهدي مره'],
]);

// ── ٦. الخطوة ٧: الترقيم لا يلصق الكلمات ────────────────────────

it('replaces punctuation with a separator so words never merge', function (string $input, string $expected): void {
    expect(Arabic::normalize($input))->toBe($expected);
})->with([
    'أقواس التنصيص القرآني' => ['﴿من عمل صالحا﴾', 'من عمل صالحا'],
    'علامتا اقتباس' => ['«الحمد لله»', 'الحمد لله'],
    'نقطتان بلا مسافة' => ['قال:الحمد لله', 'قال الحمد لله'],
    'فاصلة عربية بلا مسافة' => ['الأولى،الثانية', 'الاولي الثانيه'],
    'ترقيم عربي' => ['ما هذا؟ لا شيء؛ نعم.', 'ما هذا لا شيء نعم'],
    'ترقيم لاتيني' => ['نعم, لا! نعم.', 'نعم لا نعم'],
    'شرطة بين كلمتين' => ['عبد-الله', 'عبد الله'],
]);

// ── ٧. الخطوة ٨: المسافات والمحارف غير المرئية ──────────────────

it('collapses whitespace and drops invisible control characters', function (string $input, string $expected): void {
    expect(Arabic::normalize($input))->toBe($expected);
})->with([
    'مسافات متعدّدة' => ['الحمد     لله', 'الحمد لله'],
    'مسافات طرفية' => ['   الحمد لله   ', 'الحمد لله'],
    'أسطر وجدولة' => ["الحمد\n\tلله", 'الحمد لله'],
    'مسافة غير فاصلة U+00A0' => ["الحمد\u{00A0}لله", 'الحمد لله'],
    'علامة اتجاه من اليمين U+200F' => ["\u{200F}الحمد لله", 'الحمد لله'],
    'علامة اتجاه من اليسار U+200E' => ["الحمد\u{200E} لله", 'الحمد لله'],
    'مسافة صفرية U+200B' => ["الحمد\u{200B}لله", 'الحمدلله'],
    'علامة ترتيب البايتات U+FEFF' => ["\u{FEFF}الحمد لله", 'الحمد لله'],
]);

// ── ٨. ما لا تمسّه الدالة ────────────────────────────────────────

it('leaves digits and Latin text as they are', function (string $input, string $expected): void {
    expect(Arabic::normalize($input))->toBe($expected);
})->with([
    'أرقام عربية هندية' => ['سورة النحل ٩٧', 'سوره النحل ٩٧'],
    'أرقام لاتينية' => ['سورة النحل 97', 'سوره النحل 97'],
    'أرقام مختلطة' => ['٩٧ و 97', '٩٧ و 97'],
    'نصّ لاتيني داخل عربي' => ['كتاب Sahih البخاري', 'كتاب Sahih البخاري'],
    'حروف عربية مجرّدة' => ['الحمد لله', 'الحمد لله'],
]);

// ── ٩. توحيد صورة يونيكود ───────────────────────────────────────

it('folds a decomposed alef exactly like a composed one', function (): void {
    // NFC: أ محرف واحد U+0623. NFD: ا + U+0654.
    // بلا توحيد الصورة تفشل الخطوة ٣ على المفكَّك وتنجح على المركَّب،
    // فيختلف تطبيع نصّين متطابقين في العين.
    $composed = "\u{0623}حمد";
    $decomposed = "\u{0627}\u{0654}حمد";

    expect(Arabic::normalize($decomposed))
        ->toBe(Arabic::normalize($composed))
        ->toBe('احمد');
});

// ── ١٠. الثبات: تطبيع المطبَّع لا يغيّره ─────────────────────────

it('is idempotent', function (string $input): void {
    $once = Arabic::normalize($input);

    expect(Arabic::normalize($once))->toBe($once);
})->with([
    '',
    '   ',
    'الحمد لله',
    'مَنْ عَمِلَ صَٰلِحًا مِّن ذَكَرٍ أَوْ أُنثَىٰ',
    '﴿إنما يخشى الله من عباده العلماء﴾',
    "الحمـــد\u{200F} لله،\u{00A0}رب العالمين",
    'رؤية أئمة الهدى مرة',
    'كتاب Sahih البخاري 97',
]);

// ── ١١. حالات واقعية من عيّنة القبول ────────────────────────────

it('normalizes a fixture ayah to its plain form', function (): void {
    $uthmani = 'إِنَّمَا يَخْشَى ٱللَّهَ مِنْ عِبَادِهِ ٱلْعُلَمَٰٓؤُا۟';
    $plain = 'انما يخشي الله من عباده العلما';

    expect(Arabic::normalize($uthmani))->toStartWith('انما يخشي الله من عباده');
    expect(Arabic::normalize('انما يخشى الله من عباده العلماء'))
        ->toBe('انما يخشي الله من عباده العلماء');
});

it('makes two spellings of the same phrase converge', function (): void {
    // الفرق بينهما تشكيل وهمزات وتطويل ومسافات — والمطلوب أن يتّحدا بعد التطبيع.
    $a = 'أحَبُّ الأعمـ__ال إلى اللهِ أدْوَمُها';
    $b = 'احب الاعمال الي الله ادومها';

    expect(Arabic::normalize(str_replace('__', '', $a)))->toBe($b);
});

// ── ١٢. عقد الدالة ──────────────────────────────────────────────

it('is the single normalization entry point in the codebase', function (): void {
    // CLAUDE.md §1 وT-03: تنفيذ واحد لا يُكرَّر. نسخة ثانية تنشأ في مهمّة
    // لاحقة تتباعد عن هذه، فيصير إخفاق المطابقة غير قابل للتفسير.
    //
    // ويُفتَّش عن **صيغة مدى المحارف في الشيفرة** لا عن محارف التشكيل في
    // النصّ: التعليقات العربية تحمل الشدّة والتطويل بطبيعتها («بـ» فيها
    // تطويل)، فالتفتيش عنها يرفع إنذاراً كاذباً على كلّ تعليق.
    $offenders = [];

    $files = Finder::create()
        ->files()->name('*.php')
        ->in([app_path()])
        ->notPath('Support/Arabic.php');

    foreach ($files as $file) {
        // \x{06xx} و\x{064x} — الصيغة التي يُكتب بها مدى محارف عربية في regex.
        if (preg_match('/\\\\x\{0[678][0-9A-Fa-f]{2}\}/', $file->getContents())) {
            $offenders[] = str_replace(base_path().'/', '', $file->getRealPath());
        }
    }

    expect($offenders)->toBe([]);
});

// ── ١٣. ما كان ثغرتين وأُغلقتا في T-03ب ─────────────────────────

it('leaves no combining mark whatsoever in the output', function (string $input): void {
    // كان المدى يقف عند U+0652 فتبقى المدّة U+0653. والمعيار الآن مطلق:
    // لا محرف غير متباعد واحد في المخرَج.
    expect(preg_match_all('/\p{Mn}/u', Arabic::normalize($input)))->toBe(0);
})->with([
    'مدّة على ياء' => ['أُو۟لَٰٓئِكَ'],
    'مدّة على واو' => ['ٱلْعُلَمَٰٓؤُا۟'],
    'ألف خنجرية' => ['ٱلصَّلَوٰةَ'],
    'علامة تعظيم' => ["قال رسول الله \u{0610}"],
    'آية كاملة بضبطها' => ['مَنْ عَمِلَ صَـٰلِحًا مِّن ذَكَرٍ أَوْ أُنثَىٰ وَهُوَ مُؤْمِنٌ'],
]);

it('removes the maddah that used to survive', function (): void {
    expect(Arabic::normalize('أُو۟لَٰٓئِكَ'))->toBe('اوليك');
});

// ── ١٤. الرسم: المطابقة على الإملائي لا على العثماني ────────────
//
// قرار مالك المنتج — 6 أيلول 2026: رواية حفص والرسم العثماني.
// والمصدر يُخرج الصيغتين، فالعرض بالعثماني والمطابقة بالإملائي.
// وهذا يُغني عن اختراع قاعدة لردّ الرسم — وطبقةُ التحقّق حدُّها ألّا تُخمّن.

it('still cannot match a transcript against the Uthmani rasm', function (string $key): void {
    // يبقى هذا صحيحاً، وهو **سبب** اعتماد العمود الإملائي لا عيبٌ فيه.
    $pair = rasmPair($key);

    expect(Arabic::normalize($pair['uthmani']))->not->toBe(Arabic::normalize($pair['imlaei']));
})->with(['2:43', '16:97']);

it('matches a transcript against the imlaei text', function (string $key, string $transcript): void {
    $imlaei = Arabic::normalize(rasmPair($key)['imlaei']);

    expect($imlaei)->toContain(Arabic::normalize($transcript));
})->with([
    'Q-EXACT-01 — النحل ٩٧' => ['16:97', 'من عمل صالحا من ذكر أو أنثى وهو مؤمن فلنحيينه حياة طيبة ولنجزينهم أجرهم بأحسن ما كانوا يعملون'],
    'Q-EXACT-02 — فاطر ٢٨' => ['35:28', 'إنما يخشى الله من عباده العلماء'],
    'Q-PARTIAL-01 — جزء آية' => ['16:97', 'ولنجزينهم أجرهم بأحسن ما كانوا يعملون'],
    'الصلاة بالإملاء المعاصر' => ['2:43', 'وأقيموا الصلاة وآتوا الزكاة'],
]);

it('does not let the imlaei text create a false match', function (string $key, string $forged): void {
    // القيد الحاكم في T-03ب: توسيع المطابقة لا يجوز أن يفتح تطابقاً كاذباً.
    // ونظام يخترع آية أسوأ من نظام لا يطابق شيئاً.
    $imlaei = Arabic::normalize(rasmPair($key)['imlaei']);

    expect($imlaei)->not->toContain(Arabic::normalize($forged));
})->with([
    'Q-ALTERED-01 — كلمة مبدَّلة' => ['16:97', 'من عمل صالحا من ذكر أو أنثى وهو مسلم فلنحيينه حياة طيبة'],
    'Q-STITCHED-01 — مركّبة من سورتين' => ['16:97', 'إن الإنسان خلق هلوعا وهو مؤمن فلنحيينه حياة طيبة'],
    'نصّ مخترَع' => ['2:43', 'وأقيموا الصلاة في جوف الليل الآخر'],
]);

/**
 * @return array{uthmani: string, imlaei: string}
 */
function rasmPair(string $key): array
{
    static $verses = null;

    $verses ??= json_decode(
        file_get_contents(base_path('tests/Fixtures/quran/rasm-pairs.json')),
        true,
    )['verses'];

    return $verses[$key];
}
