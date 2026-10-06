<?php

declare(strict_types=1);

use App\Contracts\VerifierRegistry;
use App\Enums\MatchStatus;
use App\Enums\ReviewStatus;
use App\Enums\UnverifiedPolicy;
use App\Services\Verification\FixtureGate;
use App\Support\Verification\DomainPolicy;
use App\Support\Verification\EvidenceInput;
use App\Support\Verification\FixtureOutcome;
use Database\Seeders\HadithTestSeeder;
use Database\Seeders\QuranTestSeeder;

/**
 * البوّابة صفر — عيّنة الشواهد المدسوسة.
 *
 * هذا الاختبار وحده يحسم إن كان المنتج صالحاً للنشر. وهو يعمل على عيّنة
 * محلّية من المصحف وعلى مزوّد حديث تحت سيطرة الاختبار: لا شبكة، فيكون
 * حتمياً ولا يخضره انقطاعُ مزوّد ولا يحمّره.
 */
beforeEach(function (): void {
    // ★ **شريحةٌ من المدوّنة الحقيقية لا مزوّدٌ وهمي** — T-06ب.
    //   صفوف المدوّنة تحمل الإسناد مع المتن، ومتونٌ مجرّدة في مزوّدٍ مصنوع
    //   تقيس شيئاً آخر — وهذا وحده غيّر أربع حالات (T-05ب). فيتطابق ما
    //   يقيسه CI وما يقيسه `khulasah:verify-fixtures`.
    $this->seed(QuranTestSeeder::class);
    $this->seed(HadithTestSeeder::class);

    $this->gate = new FixtureGate(app(VerifierRegistry::class));

    $this->outcomes = $this->gate->run();
});

// ── كلّ حالة على حدة ────────────────────────────────────────────

it('classifies every planted fixture as the sample demands', function (): void {
    $failed = array_filter($this->outcomes, fn (FixtureOutcome $o): bool => ! $o->passed());

    $report = implode("\n", array_map(
        fn (FixtureOutcome $o): string => "  {$o->case->id} ({$o->case->note})\n    - ".implode("\n    - ", $o->failures),
        $failed,
    ));

    expect($failed)->toBe([], "أخفقت حالات في عيّنة القبول:\n{$report}");
});

it('covers every case in the sample file', function (): void {
    // حارس ضدّ صمت الاختبار: ملفّ لا يُقرأ يجعل كلّ ما فوقه يمرّ فارغاً.
    expect($this->outcomes)->toHaveCount(23);
});

// ── ★ القواعد الحاجبة — تأكيدات مستقلّة لا آثار جانبية ★ ────────

it('enforces every blocking rule in the sample', function (): void {
    foreach ($this->gate->blockingRules($this->outcomes) as $rule) {
        expect($rule['passed'])->toBeTrue("قاعدة حاجبة أخفقت: {$rule['rule']} — {$rule['detail']}");
    }
});

it('auto-passes nothing that was meant to be held', function (): void {
    // أهمّ تأكيد في المشروع: target_metrics
    // fabricated_or_altered_passed_automatically = 0.
    $leaked = array_values(array_map(
        fn (FixtureOutcome $o): string => $o->case->id,
        array_filter(
            $this->outcomes,
            fn (FixtureOutcome $o): bool => $o->case->expectsHold()
                && $o->reviewStatus === ReviewStatus::AutoPassed,
        ),
    ));

    expect($leaked)->toBe([], 'شواهد كان يجب أن تقف فمرّت آلياً: '.implode('، ', $leaked));
});

it('never substitutes a near text for one that did not match', function (): void {
    // «none تعني none» — ونظامٌ يخترع أسوأ من نظام لا يطابق شيئاً.
    $invented = array_filter(
        $this->outcomes,
        fn (FixtureOutcome $o): bool => ($o->case->expect['match_status'] ?? null) === 'none'
            && $o->result->matchedText !== null,
    );

    expect($invented)->toBe([]);
});

it('discloses the ruling of every weak or fabricated hadith it publishes', function (): void {
    // ★ **القاعدة الحاكمة بعد سياسة البيان** — §7-5.
    //   كانت «الضعيف والموضوع يقفان»، وصارت «يُنشران مبيَّنَين». **والمسوّغ
    //   هو البيان نفسه**: الآفة في نقل الضعيف موهِماً صحّته، لا في نقله
    //   مبيَّناً. فإن سقط البيان سقط المسوّغ، وصار المنتج ينشر ضعيفاً على
    //   أنّه ثابت — وهو عين ما بُني المنتج ليمنعه.
    foreach ($this->outcomes as $outcome) {
        $grade = $outcome->result->sourceMeta['grade'] ?? null;

        if (! $outcome->published() || ! in_array($grade, ['daif', 'mawdu'], true)) {
            continue;
        }

        $disclosure = (string) ($outcome->result->sourceMeta['disclosure'] ?? '');

        $marker = $grade === 'daif' ? 'ضعيف' : 'لا يصحّ';

        expect(str_contains($disclosure, $marker))->toBeTrue(
            "نُشر بلا بيان درجته: {$outcome->case->id} — «{$disclosure}»",
        );
    }
});

it('publishes nothing whose source it could not name', function (): void {
    // ★ **الضمانة الحاكمة** — §7-5: «ما جُهل مصدره لا يُنشر البتّة».
    //   وهذا ما حلّ محلّ `fabricated_or_altered_passed_automatically`.
    $policy = DomainPolicy::for(DomainPolicy::DEFAULT);

    $leaked = array_values(array_map(
        fn (FixtureOutcome $o): string => $o->case->id,
        array_filter(
            $this->outcomes,
            fn (FixtureOutcome $o): bool => $o->published() && ! $policy->hasStatedSource($o->result),
        ),
    ));

    expect($leaked)->toBe([], 'نُشر بلا درجة منصوصة: '.implode('، ', $leaked));
});

it('publishes the source wording, never the lecture wording', function (): void {
    // «لا يُنشر لفظ المحاضرة قطّ. كلّ منشورٍ لفظُ مصدره — وهذا الفرق بين
    //  منتج موثوق ومنتج ينقل خطأ المتكلّم» — §7-5، القاعدة الأولى.
    foreach ($this->outcomes as $outcome) {
        if (! $outcome->published()) {
            continue;
        }

        expect($outcome->result->matchedText)->not->toBeEmpty(
            "نُشر بلا لفظ مصدر: {$outcome->case->id}",
        );
    }
});

// ── ★ الوضعان معاً: disclose و review ★ ─────────────────────────

it('holds everything but the exact and sound when the tenant asks for review', function (): void {
    // الإعداد `tenants.on_unverified` بقيمتين، فلا يُختبر بواحدة.
    // و`review` **لا تنشر ما لا ينشره `disclose`** — هي أضيق منه لا أوسع.
    $strict = new FixtureGate(
        registry: app(VerifierRegistry::class),
        mode: UnverifiedPolicy::Review,
    );

    foreach ($strict->run() as $outcome) {
        if (! $outcome->published()) {
            continue;
        }

        expect($outcome->result->status)->toBe(MatchStatus::Exact, "مرّ غيرُ تامّ في وضع المراجعة: {$outcome->case->id}")
            ->and($outcome->result->sourceMeta['grade'] ?? 'sahih')->toBe('sahih');
    }
});

it('never turns a removed item into a published one by asking for review', function (): void {
    // **الحدّ الرابع لا يُمسّ:** الإعداد يغيّر متى تُدخَل حالة الوقوف، لا
    // ما تفعله فيها. فوضعُ المراجعة أضيق من وضع البيان في كلّ حالة.
    $strict = new FixtureGate(
        registry: app(VerifierRegistry::class),
        mode: UnverifiedPolicy::Review,
    );

    $disclosed = collect($this->outcomes)->keyBy(fn (FixtureOutcome $o): string => $o->case->id);

    foreach ($strict->run() as $outcome) {
        if ($outcome->published()) {
            expect($disclosed[$outcome->case->id]->published())->toBeTrue(
                "وضعُ المراجعة نشر ما لم ينشره وضع البيان: {$outcome->case->id}",
            );
        }
    }
});

it('refuses a bare narration formula however often the corpus repeats it', function (): void {
    // ★ T-06ب القرار الرابع. «قال رسول الله صلى الله عليه وسلم» في آلاف
    //   الصفوف، فكانت تُطابَق تامّةً **وتُنشر حديثاً صحيحاً بتخريجٍ كامل**.
    //   والطول لا يفصل: هي سبع كلمات، و«المسلم من سلم المسلمون من لسانه
    //   ويده» سبع كلمات. الفاصل أنّها مطّردة وذاك متن.
    $verifier = app(VerifierRegistry::class)->for(DomainPolicy::DEFAULT, 'hadith');

    foreach ([
        'قال رسول الله صلى الله عليه وسلم',
        'صلى الله عليه وسلم',
        'عن النبي صلى الله عليه وسلم قال',
    ] as $formula) {
        $result = $verifier->verify(new EvidenceInput(kind: 'hadith', rawText: $formula));

        expect($result->status)->toBe(MatchStatus::None, "صيغةُ روايةٍ طُوبقت حديثاً: {$formula}")
            ->and(ReviewStatus::decide($result, DomainPolicy::for(DomainPolicy::DEFAULT)))
            ->toBe(ReviewStatus::Removed);
    }
});
