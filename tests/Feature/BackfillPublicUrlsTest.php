<?php

declare(strict_types=1);

use App\Enums\Locale;
use App\Enums\OutputType;
use App\Models\Lecture;
use App\Models\Output;
use App\Models\SummaryJob;
use App\Models\Tenant;

/*
 * تصحيحُ public_url على صفوف outputs قائمة — T-128.
 */

beforeEach(function (): void {
    config()->set('khulasah.publish.cdn_url', '');
    config()->set('app.url', 'https://khulasat.io');

    $this->tenant = Tenant::factory()->create(['slug' => 'tenant-a']);
    $this->job = SummaryJob::factory()->create([
        'tenant_id' => $this->tenant->id,
        'lecture_id' => Lecture::factory()->create(['tenant_id' => $this->tenant->id])->id,
    ]);
});

function makeOutput(SummaryJob $job, string $storagePath, ?string $publicUrl, OutputType $type = OutputType::Page): Output
{
    return Output::query()->create([
        'summary_job_id' => $job->id,
        'tenant_id' => $job->tenant_id,
        'type' => $type,
        'format' => 'html',
        'storage_path' => $storagePath,
        'public_url' => $publicUrl,
        'renderer_version' => 'v1',
        'locale' => Locale::Ar,
    ]);
}

it('يصحّح رابط قرصٍ خام قديماً إلى الشكل النظيف', function (): void {
    $output = makeOutput(
        $this->job,
        'tenant-a/anuan-akhr-4/index.html',
        'https://khulasat.io/storage/tenant-a/anuan-akhr-4/index.html',
    );

    $this->artisan('khulasah:backfill-public-urls')->assertSuccessful();

    expect($output->refresh()->public_url)->toBe('https://khulasat.io/tenant-a/anuan-akhr-4');
});

it('يصحّح صفّ الكاروسيل أيضاً بلا رسمٍ ولا نداء نموذج', function (): void {
    $output = makeOutput(
        $this->job,
        'tenant-a/anuan-akhr-4/carousel/index.html',
        'https://khulasat.io/storage/tenant-a/anuan-akhr-4/carousel/index.html',
        OutputType::Carousel,
    );

    $this->artisan('khulasah:backfill-public-urls')->assertSuccessful();

    expect($output->refresh()->public_url)->toBe('https://khulasat.io/tenant-a/anuan-akhr-4/carousel');
});

it('لا يلمس صفّاً رابطُه نظيفٌ أصلاً', function (): void {
    $output = makeOutput(
        $this->job,
        'tenant-a/anuan-al-drs/index.html',
        'https://khulasat.io/tenant-a/anuan-al-drs',
    );

    $this->artisan('khulasah:backfill-public-urls')->assertSuccessful();

    expect($output->refresh()->public_url)->toBe('https://khulasat.io/tenant-a/anuan-al-drs');
});

it('لا يكتب شيئاً في --dry-run', function (): void {
    $output = makeOutput(
        $this->job,
        'tenant-a/anuan-akhr-4/index.html',
        'https://khulasat.io/storage/tenant-a/anuan-akhr-4/index.html',
    );

    $this->artisan('khulasah:backfill-public-urls --dry-run')->assertSuccessful();

    expect($output->refresh()->public_url)->toBe('https://khulasat.io/storage/tenant-a/anuan-akhr-4/index.html');
});

it('صالحٌ لإعادة التشغيل بأمان', function (): void {
    makeOutput(
        $this->job,
        'tenant-a/anuan-akhr-4/index.html',
        'https://khulasat.io/storage/tenant-a/anuan-akhr-4/index.html',
    );

    $this->artisan('khulasah:backfill-public-urls');
    $this->artisan('khulasah:backfill-public-urls')->assertSuccessful();

    expect(Output::query()->first()->public_url)->toBe('https://khulasat.io/tenant-a/anuan-akhr-4');
});
