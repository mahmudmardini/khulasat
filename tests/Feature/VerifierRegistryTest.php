<?php

declare(strict_types=1);

use App\Contracts\EvidenceVerifier;
use App\Contracts\VerifierRegistry;
use App\Enums\MatchStatus;
use App\Models\EvidenceItem;
use App\Models\SummaryJob;
use App\Models\Tenant;
use App\Services\Verification\ConfiguredVerifierRegistry;
use App\Services\Verification\HadithVerifier;
use App\Services\Verification\QuranVerifier;
use App\Support\Verification\DomainPolicy;
use App\Support\Verification\EvidenceInput;
use Tests\Fixtures\FakeHadithProvider;

function registry(): VerifierRegistry
{
    return app(VerifierRegistry::class);
}

// ── الحلّ بمفتاح (domain, kind) ─────────────────────────────────

it('resolves the verifier registered for each evidence kind', function (string $kind, string $class): void {
    expect(registry()->for(DomainPolicy::DEFAULT, $kind))->toBeInstanceOf($class);
})->with([
    ['ayah', QuranVerifier::class],
    ['hadith', HadithVerifier::class],
    ['athar', HadithVerifier::class],
]);

it('is bound to the contract, not to one implementation', function (): void {
    expect(registry())->toBeInstanceOf(ConfiguredVerifierRegistry::class)
        ->and(registry())->toBeInstanceOf(VerifierRegistry::class)
        // مفردٌ في الحاوية، فلا يُعاد بناء المزوّدين في كلّ نداء.
        ->and(registry())->toBe(app(VerifierRegistry::class));
});

it('builds one verifier for the two kinds that share it', function (): void {
    // الحديث والأثر محقّقهما واحد، وبناؤه مرّتين يعيد بناء مزوّديه بلا داعٍ.
    expect(registry()->for('islamic', 'hadith'))->toBe(registry()->for('islamic', 'athar'));
});

// ── ★ ما لا محقّق له لا يمرّ ★ ─────────────────────────────────

it('returns nothing for a kind the domain does not declare', function (string $kind): void {
    // **ولا يُقرَّب إلى أقرب محقّق.** النوع الذي لا محقّق له يعود `none`
    // ويُرفع للمراجعة — المواصفة §7-4. وعجزُنا عن التحقّق ليس شهادةً بالصحّة.
    expect(registry()->for(DomainPolicy::DEFAULT, $kind))->toBeNull();
})->with(['citation', 'statistic', 'definition', '']);

it('refuses a domain with no policy instead of guessing one', function (): void {
    expect(fn () => registry()->for('academic', 'ayah'))->toThrow(RuntimeException::class);
});

// ── المحقّق يُبنى بسياسة مجاله ──────────────────────────────────

it('builds the hadith verifier with its domain thresholds', function (): void {
    // ★ لو قرأ المحقّق عتباته من إعدادٍ عامّ لما تغيّر شيء بتغيير سياسة
    //   المجال — وهذا ما يُثبت أنّها منه لا من إعدادٍ فوقه.
    app()->when(HadithVerifier::class)
        ->needs('$providers')
        ->give(fn (): array => [FakeHadithProvider::withKnownHadiths()]);

    $quote = 'أحب الأعمال إلى الله أكثرها وإن قل';  // محرَّفة كلمةً — 0.857

    $verifier = registry()->for('islamic', 'hadith');
    expect($verifier->verify(new EvidenceInput('hadith', $quote))->status)
        ->toBe(MatchStatus::Partial);

    // مجالٌ عتبته أدنى يقرأ اللفظ نفسه مطابقةً تامّة.
    config()->set('khulasah.domains.test_loose', [
        'verifiers' => ['hadith' => HadithVerifier::class],
        'settling' => [
            'disclose' => ['statuses' => ['exact', 'partial'], 'grades' => null],
            'review' => ['statuses' => ['exact'], 'grades' => ['sahih']],
        ],
        'thresholds' => ['exact' => 0.8, 'partial' => 0.5],
    ]);

    expect(registry()->for('test_loose', 'hadith')->verify(new EvidenceInput('hadith', $quote))->status)
        ->toBe(MatchStatus::Exact);
});

it('gives every verifier it hands back the shared contract', function (): void {
    foreach (['ayah', 'hadith', 'athar'] as $kind) {
        expect(registry()->for(DomainPolicy::DEFAULT, $kind))->toBeInstanceOf(EvidenceVerifier::class);
    }
});

// ── المجال يُنسخ في الصفّ، ولا يُقرأ من الجهة كلّ مرّة ──────────

it('copies the tenant domain onto the evidence row it creates', function (): void {
    $tenant = Tenant::factory()->create(['domain' => 'test_loose']);
    $job = SummaryJob::factory()->create(['tenant_id' => $tenant->id]);

    $item = EvidenceItem::factory()->create([
        'summary_job_id' => $job->id,
        'tenant_id' => $tenant->id,
        'domain' => null,
    ]);

    expect($item->domain)->toBe('test_loose');
});

it('keeps an old verdict readable after the tenant changes domain', function (): void {
    // ★ ولهذا يُنسخ ولا يُقرأ: جهةٌ تبدّل مجالها كانت ستُعيد قراءة شواهدَ
    //   صُنّفت ونُشرت بسياسةٍ أخرى، **فيتغيّر حكمٌ صدر**.
    $tenant = Tenant::factory()->create(['domain' => 'islamic']);
    $job = SummaryJob::factory()->create(['tenant_id' => $tenant->id]);
    $item = EvidenceItem::factory()->create([
        'summary_job_id' => $job->id,
        'tenant_id' => $tenant->id,
        'domain' => null,
    ]);

    $tenant->update(['domain' => 'test_loose']);

    expect($item->fresh()->domain)->toBe('islamic');
});

it('reads its settling policy from the domain stamped on the row', function (): void {
    config()->set('khulasah.domains.test_loose', [
        'verifiers' => ['hadith' => HadithVerifier::class],
        'settling' => [
            'disclose' => ['statuses' => ['exact', 'partial'], 'grades' => null],
            'review' => ['statuses' => ['exact', 'partial'], 'grades' => null],
        ],
        'thresholds' => ['exact' => 0.9, 'partial' => 0.5],
    ]);

    $item = EvidenceItem::factory()->create(['domain' => 'test_loose']);

    expect($item->domainPolicy()->domain)->toBe('test_loose')
        ->and($item->domainPolicy()->allowsAutoPass(MatchStatus::Partial))->toBeTrue();
});
