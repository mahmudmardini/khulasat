<?php

declare(strict_types=1);

use App\Actions\Stages\RenderAndPublish;
use App\Domain\Summary\JobState;
use App\Enums\Locale;
use App\Enums\OutputType;
use App\Models\Lecture;
use App\Models\Output;
use App\Models\SummaryJob;
use App\Models\Tenant;
use Database\Seeders\ModelConfigSeeder;
use Illuminate\Support\Facades\Storage;

/*
 * ربطُ اللغات في الملفّ المنشور فعلاً — T-134.
 *
 * ★ **وهذا ما لا يكشفه اختبارُ العارض.** فالعارضُ يُرسم بعد أن تستقرّ
 * المخرَجات فيرى أخواتَه، **والخطُّ ينشر الأولى قبل أن تُرسم الأخريات**
 * («الأولى أوّلاً وقطعاً» — T-38). فتخرج الأولى بلا شريط، ويبقى الملفُّ
 * المنشور على القرص بلا رابطٍ وإن كان العارضُ سليماً.
 *
 * فالمقيسُ هنا الملفُّ المرفوع لا ما يعيده العارض.
 */

beforeEach(function (): void {
    // الترجمةُ مرحلةٌ تُنادى، فتحتاج صفَّها في `model_config` — والبوّابةُ
    // الوهميّة هي الافتراض (CLAUDE.md §2 القاعدة السابعة)، فلا نداءَ حقيقيّ.
    (new ModelConfigSeeder)->run();

    Storage::fake('public');
    config()->set('khulasah.publish.disk', 'public');
    // ولا نطاقَ حافّةٍ: الرابطُ يُبنى من القرص، فيُقرأ في الاختبار كما يُكتب.
    config()->set('khulasah.publish.cdn_url', '');

    $this->tenant = Tenant::factory()->create(['slug' => 'tenant-a']);
});

/** مهمّةٌ في `rendering` بلغتين، جاهزةٌ لـ`RenderAndPublish`. */
function twoLocaleJob(Tenant $tenant): SummaryJob
{
    $lecture = Lecture::factory()->create([
        'tenant_id' => $tenant->id,
        'title_ar' => 'عنوان الدرس',
        'locales' => ['ar', 'en'],
    ]);

    $job = SummaryJob::factory()->for($lecture)->create([
        'tenant_id' => $tenant->id,
        'state' => JobState::Rendering->value,
        'structure_json' => ['title_ar' => 'عنوان الدرس'],
        'body_json' => ['sections' => [['heading' => 'المقدّمة', 'blocks' => [['type' => 'paragraph', 'text' => 'متنُ الفقرة.']]]]],
        'body_html' => '<p class="lead">فقرة عربية.</p>',
    ]);

    return $job->fresh();
}

it('يربط الملفّ المنشور أوّلاً بأخته التي نُشرت بعده', function (): void {
    $job = twoLocaleJob($this->tenant);

    app(RenderAndPublish::class)->handle($job);

    $outputs = Output::acrossTenants()
        ->where('summary_job_id', $job->id)
        ->where('type', OutputType::Page->value)
        ->get();

    // اللغتان منشورتان — وإلّا فالمقياسُ التالي لا معنى له.
    expect($outputs)->toHaveCount(2);

    $arabic = $outputs->firstWhere(fn (Output $o): bool => $o->locale === Locale::Ar);
    $english = $outputs->firstWhere(fn (Output $o): bool => $o->locale === Locale::En);

    expect($arabic?->storage_path)->not->toBeNull()
        ->and($english?->storage_path)->not->toBeNull();

    $arabicFile = Storage::disk('public')->get((string) $arabic->storage_path);

    /*
     * ★ **والعربيةُ نُشرت أوّلاً، قبل أن توجد الإنجليزية.** فبلا إعادة
     * الرسم يبقى ملفُّها بلا شريط — وهو ما تحرسه هذه.
     */
    expect($arabicFile)->toContain('<nav class="locale-strip"')
        ->and($arabicFile)->toContain((string) $english->public_url)
        ->and($arabicFile)->toContain('English');

    // والإنجليزيةُ تحيل إلى العربية كذلك، فالربطُ متبادلٌ لا في اتّجاه.
    $englishFile = Storage::disk('public')->get((string) $english->storage_path);

    expect($englishFile)->toContain((string) $arabic->public_url);
});

it('لا يُعيد رسم شيءٍ لملخّصٍ بلغةٍ واحدة', function (): void {
    $lecture = Lecture::factory()->create([
        'tenant_id' => $this->tenant->id,
        'title_ar' => 'درسٌ بلغةٍ واحدة',
        'locales' => ['ar'],
    ]);

    $job = SummaryJob::factory()->for($lecture)->create([
        'tenant_id' => $this->tenant->id,
        'state' => JobState::Rendering->value,
        'structure_json' => ['title_ar' => 'درسٌ بلغةٍ واحدة'],
        'body_json' => ['sections' => [['heading' => 'المقدّمة', 'blocks' => [['type' => 'paragraph', 'text' => 'متنُ الفقرة.']]]]],
        'body_html' => '<p class="lead">فقرة.</p>',
    ])->fresh();

    app(RenderAndPublish::class)->handle($job);

    $output = Output::acrossTenants()
        ->where('summary_job_id', $job->id)
        ->where('type', OutputType::Page->value)
        ->sole();

    $file = Storage::disk('public')->get((string) $output->storage_path);

    // لا شريطَ ولا `hreflang`: لا أختَ لها تُحال إليها.
    expect($file)->not->toContain('<nav class="locale-strip"')
        ->and($file)->not->toContain('rel="alternate"');
});
