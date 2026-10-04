<?php

declare(strict_types=1);

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\Lang;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Validator;

/*
 * رسائل التحقّق ومفاتيح النصوص في الخادم — T-150.
 *
 * **بلاغُ مالك المنتج:** «validation.required» تحت حقلٍ ملؤه صحيح. الإطار
 * يحمل الإنجليزية وحدها، ولغتُنا الاحتياطية العربية، فمن لا ملفَّ تحقّقٍ له
 * يقرأ المفتاح الخام. ولم يلتقطه حارسٌ لأنّ الحارس الموجود يفحص `t()` في
 * الواجهة وحدها، لا `trans()` في الخادم.
 */

const PANEL_LOCALES = ['ar', 'en', 'tr', 'ru'];

/** كلّ اسم قاعدةٍ في ملفّ الإطار — هو المرجع في «ما يجب أن تغطّيه كلّ لغة». */
function frameworkRules(): array
{
    $rules = require base_path('vendor/laravel/framework/src/Illuminate/Translation/lang/en/validation.php');

    return array_keys(array_diff_key($rules, ['custom' => 1, 'attributes' => 1]));
}

it('يحمل كلّ لغةٍ من لغات اللوحة كلَّ قاعدة تحقّقٍ يعرفها الإطار', function (string $locale): void {
    $missing = [];

    foreach (frameworkRules() as $rule) {
        // بلا رجوعٍ إلى اللغة الاحتياطية: ما يُلتقط بها يُخفي العطل.
        if (! Lang::has("validation.{$rule}", $locale, false)) {
            $missing[] = $rule;
        }
    }

    expect($missing)->toBe([]);
})->with(PANEL_LOCALES);

it('يسمّي كلّ حقلٍ تعرضه الشاشات بلغة القارئ لا باسمه البرمجيّ', function (string $locale): void {
    $fields = [
        'email', 'password', 'password_confirmation', 'name', 'role', 'name_ar', 'name_ar_full',
        'name_latin', 'palette', 'template', 'locales', 'youtube_url', 'social_url', 'logo',
        'disclaimer_text', 'source_url', 'source_file', 'transcript_text', 'title_ar', 'speaker_name',
        'speaker_title', 'venue_mode', 'source_kind',
    ];

    $missing = array_values(array_filter(
        $fields,
        fn (string $field): bool => ! Lang::has("validation.attributes.{$field}", $locale, false),
    ));

    expect($missing)->toBe([]);
})->with(PANEL_LOCALES);

it('لا تخرج رسالة «مطلوب» مفتاحاً خاماً بأيّ لغة', function (string $locale): void {
    app()->setLocale($locale);

    $message = Validator::make([], ['speaker_name' => 'required'])->errors()->first('speaker_name');
    // الاسم البرمجيّ لا يظهر — ومن يقرؤه يقرأ «speaker_name» لا «اسم الملقي».
    expect($message)->not->toContain('validation.')->not->toContain('speaker_name');
})->with(PANEL_LOCALES);

it('يعرض «مطلوب» بالعربية باسم الحقل', function (): void {
    app()->setLocale('ar');

    expect(Validator::make([], ['speaker_name' => 'required'])->errors()->first('speaker_name'))
        ->toBe('اسم الملقي مطلوب.');
});

/**
 * **كلّ مفتاحٍ ثابتٍ يطلبه الخادم بـ`trans()` و`__()` موجودٌ في `lang/ar`.**
 * وهو الحارس الذي ينقص نظيرَه في الواجهة (`DesignSystemTest`): مفتاحٌ ضاع
 * يعود خاماً في رسالة خطأ، ولا يعلم به أحدٌ حتى يراه مستخدم.
 */
it('يجد كلّ مفتاح نصّ يطلبه الخادم', function (): void {
    $missing = [];

    // `app/` و`resources/views/` (Blade تنتهي بـ`.blade.php` فامتدادُها `php` كذلك).
    foreach ([app_path(), resource_path('views')] as $root) {
        $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root));

        foreach ($files as $file) {
            if (! $file->isFile() || $file->getExtension() !== 'php') {
                continue;
            }

            $source = (string) file_get_contents($file->getPathname());

            // الثابت وحده: `trans('file.key')` بلا استيفاءٍ ولا تركيب — وما يليه نقطةٌ (`'a.b_'.$x`) مبنيٌّ لا يُفحص.
            preg_match_all("/\b(?:trans|trans_choice|__)\(\s*'([a-z][a-z0-9_]*(?:\.[a-z0-9_*]+)+)'\s*[,)]/", $source, $matches);

            foreach ($matches[1] as $key) {
                if (! Lang::has($key, 'ar', false)) {
                    $missing[] = basename($file->getPathname()).': '.$key;
                }
            }
        }
    }

    expect($missing)->toBe([]);
});

// صار الرفعُ مصدراً مقبولاً (§5-أ-4-ب)، **ورسالتُه بالعربية لا مفتاحاً خاماً**.
// وتفصيلُ الرفع في `LectureUploadTest`.
it('يطلب الخادم الملفَّ بالعربية حين يُختار الرفع بلا ملفّ', function (): void {
    Queue::fake();

    $tenant = Tenant::factory()->create();
    $user = User::factory()->owner()->for_($tenant)->create();

    $this->actingAs($user)->post('/panel/lectures', [
        'source_kind' => 'upload',
        'title_ar' => 'درس',
        'speaker_name' => 'الملقي',
        'venue_mode' => 'institution',
    ])->assertSessionHasErrors(['source_file' => 'اختر ملفّ الصوت أو الفيديو.'])
        ->assertSessionDoesntHaveErrors('source_kind');
});
