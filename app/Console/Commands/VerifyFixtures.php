<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Contracts\VerifierRegistry;
use App\Models\Hadith;
use App\Models\QuranAyah;
use App\Services\Verification\FixtureGate;
use App\Support\Verification\DomainPolicy;
use App\Support\Verification\FixtureOutcome;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;

/**
 * Runs gate zero against real data — المهمّة T-06.
 *
 * الاختبار في CI يعمل على عيّنة محلّية ومزوّد وهمي فيكون حتمياً. وهذا
 * الأمر يشغّل العيّنة نفسها على **المصحف المبذور والمزوّدين المضبوطين**،
 * فيكشف ما لا يكشفه الاختبار: لفظاً في العيّنة يخالف المصدر، أو حكماً
 * تغيّر عند المزوّد، أو مزوّداً ساقطاً.
 *
 * ولا يُعدَّل ملفّ العيّنة ليمرّ هذا الأمر — بل يُعرض ما أخفق على المستخدم،
 * ولا يُصحَّح إلّا ما ثبت أنّ لفظه هو الخطأ.
 */
class VerifyFixtures extends Command
{
    protected $signature = 'khulasah:verify-fixtures
                            {--path= : مسار ملفّ عيّنة بديل}
                            {--domain= : مجال يُشغَّل به بدل الشرعي}';

    protected $description = 'تشغيل عيّنة الشواهد المدسوسة — البوّابة صفر';

    public function handle(): int
    {
        $this->warnIfUnready();

        // **المحقّقون من السجلّ لا مبنيّون هنا** — §7-4. فما يقيسه المشغّل
        // هو ما يعمل في الإنتاج، بمزوّديه وسياسة مجاله كما هي.
        $gate = new FixtureGate(
            registry: app(VerifierRegistry::class),
            domain: (string) ($this->option('domain') ?: DomainPolicy::DEFAULT),
            path: $this->option('path'),
        );

        $outcomes = $gate->run();

        $this->table(
            ['المعرّف', 'المنتظر', 'الفعلي', 'الحكم'],
            array_map($this->row(...), $outcomes),
        );

        $this->failureDetail($outcomes);
        $rulesFailed = $this->blockingRules($gate, $outcomes);

        $failed = count(array_filter($outcomes, fn (FixtureOutcome $o): bool => ! $o->passed()));

        $this->newLine();

        if ($failed === 0 && $rulesFailed === 0) {
            $this->info(sprintf('البوّابة صفر مفتوحة — %d حالة مرّت كلّها.', count($outcomes)));

            return self::SUCCESS;
        }

        $this->error(sprintf(
            'البوّابة صفر مغلقة — %d حالة أخفقت، و%d قاعدة حاجبة.',
            $failed,
            $rulesFailed,
        ));

        return self::FAILURE;
    }

    /**
     * @return array{string, string, string, string}
     */
    private function row(FixtureOutcome $outcome): array
    {
        return [
            $outcome->case->id,
            $outcome->expected(),
            $outcome->actual(),
            $outcome->passed() ? '<fg=green>نجح</>' : '<fg=red>أخفق</>',
        ];
    }

    /**
     * @param  list<FixtureOutcome>  $outcomes
     */
    private function failureDetail(array $outcomes): void
    {
        foreach ($outcomes as $outcome) {
            if ($outcome->passed()) {
                continue;
            }

            $this->newLine();
            $this->line("<fg=red>✗</> {$outcome->case->id} — {$outcome->case->note}");

            foreach ($outcome->failures as $failure) {
                $this->line("    {$failure}");
            }
        }
    }

    /**
     * @param  list<FixtureOutcome>  $outcomes
     */
    private function blockingRules(FixtureGate $gate, array $outcomes): int
    {
        $this->newLine();
        $this->line('<options=bold>القواعد الحاجبة</>');

        $failed = 0;

        foreach ($gate->blockingRules($outcomes) as $rule) {
            $mark = $rule['passed'] ? '<fg=green>✓</>' : '<fg=red>✗</>';
            $this->line("  {$mark} {$rule['rule']}");
            $this->line("      {$rule['detail']}");

            $failed += $rule['passed'] ? 0 : 1;
        }

        return $failed;
    }

    /**
     * تحذير قبل التقرير إن كانت المصادر غائبة.
     *
     * بلا هذا يقرأ المشغّل «البوّابة صفر مغلقة» فيظنّ المحقّقين معطوبين،
     * والسبب أنّ المصحف لم يُبذر أو أنّ لا مزوّد حديث مضبوطاً. والفرق بين
     * الأمرين هو الفرق بين خللٍ يُصلَح وبيئةٍ لم تُهيَّأ.
     */
    private function warnIfUnready(): void
    {
        $missing = [];

        if (QuranAyah::query()->count() === 0) {
            $missing[] = 'المصحف غير مبذور — شغّل php artisan khulasah:seed-quran.';
        }

        if (! Schema::hasTable('hadith_corpus') || Hadith::query()->count() === 0) {
            $missing[] = 'مدوّنة الحديث غير مبذورة — شغّل php artisan khulasah:seed-hadith.';
        }

        if ($missing === []) {
            return;
        }

        $this->warn('المصادر ناقصة، وما يلي ليس حكماً على المحقّقين:');

        foreach ($missing as $line) {
            $this->line("  • {$line}");
        }

        $this->newLine();
    }
}
