<?php

declare(strict_types=1);

use App\Domain\Summary\JobState;
use App\Enums\OutputType;
use App\Models\Lecture;
use App\Models\Output;
use App\Models\SummaryJob;
use App\Models\Tenant;

/*
 * صفحةُ الملخّص باللغة الأولى — T-216.
 *
 * المصدرُ الوحيد لـ«رابط هذا الملخّص»: يكتبه الكاروسيلُ وحزمةُ الصور، وتقارنه
 * المعاينة. فإن اختلفوا أُخفيت صورٌ سليمة.
 */

beforeEach(function (): void {
    $tenant = Tenant::factory()->create(['plan' => 'business']);
    $this->lecture = Lecture::factory()->create(['tenant_id' => $tenant->id, 'title_ar' => 'عنوان الدرس']);
    $this->job = SummaryJob::factory()->for_($this->lecture)->inState(JobState::Published)->create();
});

function pageRow(SummaryJob $job, string $locale): Output
{
    return Output::query()->create([
        'summary_job_id' => $job->id,
        'tenant_id' => $job->tenant_id,
        'type' => OutputType::Page->value,
        'locale' => $locale,
        'format' => OutputType::Page->format()->value,
        'public_url' => "https://khulasat.io/tenant/anuan-al-drs/{$locale}",
        'rendered_at' => now(),
        'renderer_version' => '1.0.0',
    ]);
}

it('يأخذ صفحةَ اللغة الأولى أيّاً كان ترتيبُ الإدراج', function (): void {
    $this->lecture->forceFill(['locales' => ['ar', 'en', 'tr']])->save();

    pageRow($this->job, 'en');
    pageRow($this->job, 'tr');
    $ar = pageRow($this->job, 'ar');

    expect($this->job->primaryLocale()->value)->toBe('ar')
        ->and($this->job->primaryPage()?->id)->toBe($ar->id);
});

it('يأخذ الإنجليزية لملخّصٍ إنجليزيّ وحده', function (): void {
    $this->lecture->forceFill(['locales' => ['en']])->save();

    pageRow($this->job, 'tr');
    $en = pageRow($this->job, 'en');

    expect($this->job->primaryPage()?->id)->toBe($en->id);
});

it('يسقط إلى لغة المصدر إن غابت صفحةُ الأولى، ثمّ إلى أقدم صفحة', function (): void {
    $this->lecture->forceFill(['locales' => ['en']])->save();

    $tr = pageRow($this->job, 'tr');
    $ar = pageRow($this->job, 'ar');

    expect($this->job->primaryPage()?->id)->toBe($ar->id);

    $ar->delete();

    expect($this->job->primaryPage()?->id)->toBe($tr->id);
});

it('لا صفحةَ قبل النشر', function (): void {
    expect($this->job->primaryPage())->toBeNull();
});
