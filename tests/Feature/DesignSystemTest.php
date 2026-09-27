<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Lang;

/*
 * نظام التصميم — SCREENS.md §القسم الأول، وT-15ب.
 *
 * ثلاثة تُختبر هنا لأنّها تُخرَق صامتةً: **التباين** لا يُرى خرقُه إلا بمن
 * لا يقرأ، و**النصّ داخل مكوّن** يمرّ في المراجعة البصرية، و**قيمة رمزٍ
 * تُكتب مباشرةً** بدل المتغيّر تفترق عن أصلها بعد أشهر.
 */

/**
 * نسبة التباين بحساب WCAG 2.1 — الإضاءة النسبية.
 *
 * @param  string  $a  لون بصيغة #RRGGBB
 * @param  string  $b  لون بصيغة #RRGGBB
 */
function contrast(string $a, string $b): float
{
    $luminance = static function (string $hex): float {
        $hex = ltrim($hex, '#');

        $channels = array_map(
            static function (string $pair): float {
                $value = hexdec($pair) / 255;

                // gamma expansion — WCAG 2.1 relative luminance
                return $value <= 0.03928
                    ? $value / 12.92
                    : (($value + 0.055) / 1.055) ** 2.4;
            },
            str_split($hex, 2),
        );

        return 0.2126 * $channels[0] + 0.7152 * $channels[1] + 0.0722 * $channels[2];
    };

    $first = $luminance($a);
    $second = $luminance($b);

    [$light, $dark] = $first > $second ? [$first, $second] : [$second, $first];

    return ($light + 0.05) / ($dark + 0.05);
}

/** الرموز كما تُقرأ من `resources/css/app.css` — لا كما تُكتب هنا. */
function tokens(): array
{
    $css = (string) file_get_contents(base_path('resources/css/app.css'));

    preg_match_all('/^\s*(--[a-z-]+):\s*(#[0-9a-f]{6});/mi', $css, $matches, PREG_SET_ORDER);

    return array_column($matches, 2, 1);
}

it('يعرّف كل رمز لون في SCREENS.md', function (): void {
    $required = [
        '--bg', '--surface', '--surface-alt', '--border', '--border-strong',
        '--text', '--text-muted', '--text-faint',
        '--primary', '--primary-hover', '--accent',
        '--info', '--success', '--warning', '--danger',
    ];

    expect(array_keys(tokens()))->toContain(...$required);
});

/**
 * «تباين 4.5:1 على الأقل» — §إتاحة.
 *
 * ويُقاس كلّ نصّ على السطح الذي يقع عليه فعلاً، لا على الأبيض المطلق.
 * و`--text-faint` تُقاس أيضاً: خفوتُها لا يُعفيها، فهي نصٌّ يُقرأ.
 */
it('يحقّق تباين 4.5:1 لكل نصّ على سطحه', function (string $text, string $surface): void {
    $tokens = tokens();

    expect(contrast($tokens[$text], $tokens[$surface]))
        ->toBeGreaterThanOrEqual(4.5, "التباين بين {$text} و{$surface} دون 4.5:1");
})->with([
    ['--text', '--bg'],
    ['--text', '--surface'],
    ['--text', '--surface-alt'],
    ['--text-muted', '--bg'],
    ['--text-muted', '--surface'],
    ['--text-muted', '--surface-alt'],
    ['--text-faint', '--bg'],
    ['--text-faint', '--surface'],
    ['--text-faint', '--surface-alt'],
]);

/** الأفعال الرئيسية: نصّ أبيض على الأخضر، وعلى حالته المحوَّمة. */
it('يحقّق تباين الزرّ الرئيسي وحالاته', function (string $token): void {
    expect(contrast(tokens()[$token], '#ffffff'))
        ->toBeGreaterThanOrEqual(4.5, "التباين بين {$token} والأبيض دون 4.5:1");
})->with(['--primary', '--primary-hover', '--danger', '--warning']);

/**
 * الرموز تطابق الهوية البصرية الثانية — T-100.
 *
 * والتباينُ وحده لا يحرسها: لونٌ آخر يمرّ بالتباين ويخالف الهوية. فتُثبَّت
 * القيم نفسُها، ومن غيّر واحدةً منها غيّر الهويةَ لا رمزاً.
 */
it('يطابق رموز الهوية البصرية الثانية', function (): void {
    $tokens = array_map('strtolower', tokens());

    expect($tokens['--primary'])->toBe('#243b6b')
        ->and($tokens['--primary-hover'])->toBe('#152444')
        ->and($tokens['--primary-tint'])->toBe('#e9edf5')
        ->and($tokens['--accent'])->toBe('#a87c33')
        ->and($tokens['--night'])->toBe('#0f192f')
        ->and($tokens['--night-mark'])->toBe('#8aa8dc')
        // الأخضر «نجاحٌ» وحده بعد أن خرج من الهوية — §٠٢.
        ->and($tokens['--success'])->toBe('#2f6b4f');
});

/** الأسطح الجديدة: الحبريّ على خافته في الحالة النشطة، والأبيضُ والقوسان على شريط المشرف. */
it('يحقّق تباين الحالة النشطة وشريط المشرف', function (string $text, string $surface): void {
    $tokens = tokens();
    $hex = static fn (string $value): string => $tokens[$value] ?? $value;

    expect(contrast($hex($text), $hex($surface)))
        ->toBeGreaterThanOrEqual(4.5, "التباين بين {$text} و{$surface} دون 4.5:1");
})->with([
    ['--primary', '--primary-tint'],
    ['#ffffff', '--night'],
    ['--night-mark', '--night'],
]);

/**
 * لا لونَ مكتوباً في المكوّنات والهياكل — كلُّه من الرموز. T-100.
 *
 * كان شريطُ التقدّم في `app.tsx` يحمل `#1B4D3E` مكتوباً، فكان سيبقى
 * أخضرَ بعد أن تبدّل كلُّ ما حوله. واللونُ المكتوب يفترق عن رمزه عند
 * أوّل تبديل، ولا يُرى افتراقُه إلّا في لقطة.
 */
it('لا يكتب لوناً صريحاً في المكوّنات والهياكل', function (): void {
    $offenders = [];

    foreach ([...uiSourceFiles(), base_path('resources/js/app.tsx')] as $file) {
        $stripped = preg_replace('#/\*.*?\*/|//[^\n]*#s', '', (string) file_get_contents($file)) ?? '';

        if (preg_match('/#[0-9a-f]{3}(?:[0-9a-f]{3})?\b/i', $stripped) === 1) {
            $offenders[] = basename($file);
        }
    }

    expect($offenders)->toBe([], 'لونٌ مكتوب داخل: '.implode(' · ', $offenders));
});

/** الرمز في التبويب، والخطُّ المقصور على الشعار — في كلّ شاشة. T-100. */
it('يحمّل أيقونة الرمز وخطَّ الشعار', function (): void {
    expect(public_path('favicon.svg'))->toBeFile();

    $this->get('/panel/login')
        ->assertOk()
        ->assertSee('href="/favicon.svg"', false)
        ->assertSee('family=Reem+Kufi:wght@600&text=', false);
});

/**
 * `needs_review` وحدها بخلفية ملوّنة — §الحالات وألوانها. فيُختبر نصُّها
 * الأبيض على `--warning`، وهو ما يفعله `StatusBadge`.
 */
it('يحقّق تباين شارة بانتظار المراجعة', function (): void {
    expect(contrast(tokens()['--warning'], '#ffffff'))->toBeGreaterThanOrEqual(4.5);
});

/**
 * ملفّات المكوّنات والهيكل.
 *
 * **بلا `GLOB_BRACE`**: الثابت غير معرَّف في بناء PHP على musl — وهو بناء
 * حاوية المشروع — فكانت الحارسات الأربع تُخطئ لا تفشل. وخطأٌ في حارسٍ
 * يُقرأ عطلاً في البيئة فيُتجاوَز، فلا يحرس شيئاً.
 *
 * @return list<string>
 */
function uiSourceFiles(): array
{
    return array_merge(
        glob(base_path('resources/js/Components/*.tsx')) ?: [],
        glob(base_path('resources/js/Layouts/*.tsx')) ?: [],
    );
}

/**
 * ملفّات الشاشات، بمجلّداتها الفرعية — `Pages/Public/Complaint.tsx` وأخواتها.
 *
 * ★ **وتُفحص مفاتيحُها كالمكوّنات** — T-149. فنموذجُ الاعتراض طلب
 * `complaint.title` بلا اسم ملفّه منذ T-24، ورُسم بمفاتيحَ عارية على
 * الإنتاج، **لأنّ الحارس لم يكن يقرأ `Pages/` أصلاً**.
 *
 * @return list<string>
 */
function uiPageFiles(): array
{
    $files = [];

    $pages = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(base_path('resources/js/Pages')));

    foreach ($pages as $file) {
        if ($file->isFile() && $file->getExtension() === 'tsx') {
            $files[] = $file->getPathname();
        }
    }

    sort($files);

    return $files;
}

/** كل ملفّات `lang/ar` التي تُبثّ إلى الواجهة. */
function uiLangFiles(): array
{
    return ['common', 'jobs', 'review', 'billing'];
}

/**
 * «كل نصّ من lang/ar. **لا نصّ مكتوب داخل مكوّن، ولا حتى (حفظ)**»
 * — §القواعد العامّة.
 *
 * ويُفحص هذا آلياً لأنّه يُخرَق بحسن نيّة: كلمةٌ واحدة تُكتب مباشرةً في
 * ساعة عجلة، فيصير في المنتج نصٌّ لا يُراجَع لغوياً ولا يُترجَم.
 */
/**
 * اسمُ اللغة بلسانها — **الاستثناءُ الوحيد من قاعدة «لا نصَّ في مكوّن»** (T-137).
 *
 * ★ **وعلّةُ القاعدة أنّ نصَّ الواجهة يتبدّل باللغة**، فيُجمع في `lang/`
 * ليُترجَم مرّةً في موضعٍ واحد. **واسمُ اللغة لا يتبدّل**: «Türkçe» هي هي
 * في الأربع، و«العربية» كذلك — فوضعُها في أربعة ملفّاتٍ بقيمةٍ واحدة
 * تكرارٌ لا تجميع، وهو نقضٌ للعلّة باسم القاعدة.
 *
 * وحجّةُ هذا مكتوبةٌ في صدر `LocaleSwitcher.tsx` من T-133، **وكان الحارسُ
 * يردّها** — فيُرخَّص له بها مُسمّاةً لا مُطلقة: يُنزَع ما كان قيمةَ `native`
 * وحدها، فيبقى كلُّ نصٍّ آخر في المكوّن مرفوضاً كما كان.
 *
 * **ولا تُوسَّع هذه بغير أسماء اللغات.** ومن أراد استثناءً ثانياً فليكتب
 * علّته كما كُتبت هذه، ولا يُلحقه بها.
 */
function stripLocaleNativeNames(string $source): string
{
    return preg_replace("/\bnative:\s*'[^']*'/", "native: ''", $source) ?? $source;
}

it('لا يكتب نصّاً عربياً داخل أيّ مكوّن', function (): void {
    $files = uiSourceFiles();

    expect($files)->not->toBeEmpty();

    $offenders = [];

    foreach ($files as $file) {
        $source = (string) file_get_contents($file);

        // التعليقات عربية بأمر CLAUDE.md §1، فتُنزع قبل الفحص.
        $stripped = preg_replace('#/\*.*?\*/|//[^\n]*#s', '', $source) ?? '';

        // **واسمُ اللغة بلسانها يُنزع كذلك** — T-137، وهو الاستثناءُ الوحيد.
        $stripped = stripLocaleNativeNames($stripped);

        if (preg_match('/[\x{0600}-\x{06FF}]/u', $stripped) === 1) {
            $offenders[] = basename($file);
        }
    }

    expect($offenders)->toBe([], 'نصّ عربي مكتوب داخل: '.implode(' · ', $offenders));
});

/*
 * ★ **حارسٌ على الاستثناء نفسه** — T-137.
 *
 * فثقبٌ في قاعدةٍ يتّسع بالاستعمال: من رأى «الحارس يمرّ على عربيةٍ هنا»
 * أضاف إليها. وهذا يُثبت أنّ المنزوع قيمةُ `native` وحدها، وأنّ عربيةً
 * في أيّ موضعٍ آخر — ولو في السطر نفسه — تبقى مردودة.
 */
it('لا يوسّع استثناءَ أسماء اللغات إلى غيرها', function (): void {
    $offending = <<<'TSX'
    const LOCALES = [{ value: 'ar', native: 'العربية', label: 'اختر لغتك' }];
    TSX;

    expect(stripLocaleNativeNames($offending))
        ->toContain('اختر لغتك')
        ->and(stripLocaleNativeNames($offending))->not->toContain('العربية');

    // والمكوّن الحقيقي: بعد النزع لا تبقى فيه عربيةٌ خارج التعليقات.
    $source = (string) file_get_contents(resource_path('js/Components/LocaleSwitcher.tsx'));
    $stripped = stripLocaleNativeNames((string) preg_replace('#/\*.*?\*/|//[^\n]*#s', '', $source));

    expect(preg_match('/[\x{0600}-\x{06FF}]/u', $stripped))->toBe(0);
});

/** كل مفتاح يطلبه مكوّن أو شاشة موجودٌ فعلاً في `lang/ar`. */
it('يجد كل مفتاح نصّ تطلبه المكوّنات والشاشات', function (): void {
    $files = [...uiSourceFiles(), ...uiPageFiles()];

    $missing = [];

    foreach ($files as $file) {
        $source = (string) file_get_contents($file);

        // t('a.b.c') الثابتة وحدها — والمبنيّة بقالب تُفحص بجذرها.
        preg_match_all("/\bt\('([a-z_.]+)'/", $source, $matches);

        foreach ($matches[1] as $key) {
            if (! Lang::has($key)) {
                $missing[] = basename($file).': '.$key;
            }
        }
    }

    expect($missing)->toBe([]);
});

/**
 * المفاتيح المبنيّة بقالب — `t(`jobs.status.${status}`)` — تُفحص بتعدادها.
 * فالنوع في TypeScript يمنع قيمةً غريبة، ولا يمنع مفتاحاً ناقصاً في العربية.
 */
it('يترجم كل حالة وكل مرحلة', function (): void {
    $statuses = [
        'queued', 'transcribing', 'cleaning', 'structuring', 'extracting',
        'verifying', 'writing', 'rendering',
        'needs_review', 'published', 'failed', 'unpublished',
    ];

    foreach ($statuses as $status) {
        expect(Lang::has("jobs.status.{$status}"))->toBeTrue("ينقص jobs.status.{$status}");
    }

    foreach (['transcribing', 'cleaning', 'structuring', 'extracting', 'verifying', 'writing', 'rendering'] as $step) {
        expect(Lang::has("jobs.steps.{$step}"))->toBeTrue("ينقص jobs.steps.{$step}");
    }

    foreach (['done', 'active', 'pending', 'failed'] as $state) {
        expect(Lang::has("jobs.step_state.{$state}"))->toBeTrue("ينقص jobs.step_state.{$state}");
    }
});

/**
 * «لا يرى كلمة (توكن)، ولا اسم نموذج» — المبدأ الحاكم في صدر SCREENS.md.
 * ولا رموز تعبيرية — §القواعد العامّة.
 */
it('لا يسرّب مصطلحاً تقنياً ولا رمزاً تعبيرياً إلى نصوص الواجهة', function (): void {
    foreach (uiLangFiles() as $file) {
        $flat = json_encode(trans($file), JSON_UNESCAPED_UNICODE);

        expect($flat)->not->toContain('توكن');
        expect($flat)->not->toContain('token');
        expect($flat)->not->toMatch('/[\x{1F300}-\x{1FAFF}\x{2600}-\x{27BF}]/u');
    }
});

/**
 * التنقّل بلوحة المفاتيح — §إتاحة.
 *
 * ويُقاس بما يُفحص ثابتاً: **لا `tabindex` موجب**، فهو يكسر ترتيب الصفحة
 * الطبيعي ويُقدّم عنصراً على ما قبله. والسالب مسموح، وهو ما تستعمله
 * `PalettePicker` في مجموعة الاختيار.
 */
it('لا يستعمل tabindex موجباً', function (): void {
    $files = uiSourceFiles();

    foreach ($files as $file) {
        expect((string) file_get_contents($file))
            ->not->toMatch('/tabIndex=\{[1-9]/', basename($file).' فيه tabindex موجب');
    }
});

/**
 * كل زرّ أيقونة بلا نصّ ظاهر له `aria-label`.
 *
 * وهذا أكثر ما يسقط: الزرّ يُرى فيُظنّ مفهوماً، وقارئ الشاشة يقول «زرّ».
 */
it('يسمّي كل زرّ أيقونة', function (): void {
    $files = uiSourceFiles();

    foreach ($files as $file) {
        $source = (string) file_get_contents($file);

        // كل <svg> داخل <button> إمّا بجانبه نصّ، وإمّا للزرّ aria-label.
        preg_match_all('/<button\b[^>]*>(.*?)<\/button>/s', $source, $buttons, PREG_SET_ORDER);

        foreach ($buttons as $button) {
            // `<Icon>` مضافةً إلى `<svg>`: منذ T-27 تُكتب الأيقونة مكوّناً
            // لا وسماً، فكان الحارس يمرّ على أزرارها كأن لا أيقونة فيها.
            if (! str_contains($button[1], '<svg') && ! str_contains($button[1], '<Icon')) {
                continue;
            }

            /*
             * و`<span` بسماته: النصّ الظاهر قد يكون داخل span موسومة بأصناف.
             *
             * ★ **و`{…native}` اسمُ اللغة بلسانها** — T-137. وهو نصٌّ ظاهرٌ
             * يقرؤه قارئُ الشاشة، فالزرُّ مسمًّى فعلاً؛ وغيابُه من الأنماط
             * كان يردُّ زرّاً صحيحاً. و`native` وحدها تُقبل لا كلُّ تعبير:
             * `{icon}` يبقى مرفوضاً كما كان.
             */
            $hasText = preg_match('/\{t\(|\{children\}|\{label|\{[a-z]+\.native\}|<span/', $button[1]) === 1;
            $hasLabel = str_contains($button[0], 'aria-label');

            expect($hasText || $hasLabel)
                ->toBeTrue(basename($file).': زرّ أيقونة بلا اسم');
        }
    }
});
