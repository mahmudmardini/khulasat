<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Enums\Role;
use App\Enums\UnverifiedPolicy;
use App\Models\Tenant;
use App\Models\User;
use App\Support\Auth\JudgeAccess;
use App\Support\TenantContext;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Create the judges' trial institution and its three accounts — T-186.
 *
 * يُعاد بلا ضرر: ما وُجد يبقى كما هو، **وكلماتُ المرور لا تُبدَّل** إلّا بـ
 * `--reset`، فلا يُخرَج محكّمٌ دخل بالنموذج من حسابه بإعادة الأمر.
 *
 * **والأسماءُ تسمياتٌ محايدة** — لا جهة حقيقية ولا شخص.
 */
class SeedJudges extends Command
{
    protected $signature = 'khulasah:seed-judges
                            {--reset : كلماتُ مرورٍ جديدة للحسابات الثلاثة}';

    protected $description = 'ينشئ جهة التجربة وحسابات لجنة التحكيم الثلاثة للدخول بنقرة — T-186';

    /**
     * حدودٌ تتّسع لتجربة اللجنة طوال التقييم **ولا تفتح الإنفاق**: وسقفُ
     * الإنفاق العامّ (`SPEND_CAP_*`) فوقها كلّها.
     */
    private const LIMITS = [
        'monthly_quota' => 30,
        'daily_cap' => 10,
        'max_lecture_minutes' => 90,
        'transcription_minutes_quota' => 300,
        'regenerations_per_summary' => 2,
    ];

    public function handle(TenantContext $context): int
    {
        $passwords = $context->withoutScope(fn (): array => DB::transaction(function (): array {
            $tenant = Tenant::query()->firstOrCreate(
                ['slug' => JudgeAccess::TENANT_SLUG],
                ['name_ar' => 'جهة التجربة', 'on_unverified' => UnverifiedPolicy::Disclose] + self::LIMITS,
            );

            $names = ['admin' => 'مشرف المنصّة', 'owner' => 'مالك الجهة', 'editor' => 'محرّر الجهة'];
            $passwords = [];

            foreach (JudgeAccess::ACCOUNTS as $role => $email) {
                $user = User::query()->where('email', $email)->first();

                if ($user !== null && ! $this->option('reset')) {
                    continue;
                }

                $password = Str::password(16, symbols: false);
                $passwords[$role] = [$email, $password];

                ($user ?? new User)->forceFill([
                    'email' => $email,
                    // المشرفُ بلا جهة — وهو ما يجعله مشرفاً (`User::isSuperAdmin`).
                    'tenant_id' => $role === 'admin' ? null : $tenant->id,
                    'name' => $names[$role],
                    'password' => Hash::make($password),
                    'role' => $role === 'editor' ? Role::Editor : Role::Owner,
                    'email_verified_at' => now(),
                ])->save();
            }

            return $passwords;
        }));

        if ($passwords === []) {
            $this->info('الحساباتُ الثلاثة موجودة. و--reset يضع لها كلماتٍ جديدة.');

            return self::SUCCESS;
        }

        // تُطبع مرّةً هنا ولا تُحفظ في مكانٍ آخر — للدخول بالنموذج إن أُريد.
        $this->table(['الدور', 'البريد', 'كلمة المرور'], array_map(
            static fn (string $role, array $row): array => [$role, ...$row],
            array_keys($passwords),
            $passwords,
        ));

        return self::SUCCESS;
    }
}
