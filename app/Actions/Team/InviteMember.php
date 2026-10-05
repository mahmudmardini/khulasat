<?php

declare(strict_types=1);

namespace App\Actions\Team;

use App\Enums\Role;
use App\Models\TeamInvitation;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * يدعو عضواً إلى جهة — SCREENS.md §10، والمهمّة T-33.
 *
 * ★ **والمخرَج رابطٌ يُنسخ، لا بريدٌ وحده.**
 *
 * فالبريد لا يصل دائماً — مرشّحُ المهملات، وعنوانٌ كُتب خطأً، ومرسِلٌ لم
 * يُضبط بعد. **وهذه الجهات تتواصل بالمراسلة الفورية أكثر من البريد أصلاً.**
 * فمن دعا رأى الرابط في شاشته ونسخه وأرسله بما شاء، ولا يقف الفريقُ كلُّه
 * على رسالةٍ لا يعلم أوصلت أم لا.
 */
final class InviteMember
{
    /** أسبوعٌ — رابطُ دعوةٍ يُفتح بعد شهر رابطٌ منسيّ. */
    public const VALID_DAYS = 7;

    /**
     * @return array{invitation: TeamInvitation, token: string} والرمز الخامّ **مرّةً واحدة**.
     */
    public function handle(Tenant $tenant, string $email, Role $role, User $actor): array
    {
        $email = Str::lower(trim($email));

        /*
         * **ولا يُدعى من هو في جهةٍ أصلاً.** فالبريد فريدٌ في `users`،
         * فقبولُ الدعوة يخفق حينها برسالة قاعدة بيانات لا يفهمها أحد —
         * **بعد أن أُرسل الرابط وانتظر صاحبه**.
         */
        if (User::acrossTenants()->where('email', $email)->exists()) {
            throw new RuntimeException(trans('team.errors.already_registered'));
        }

        // ودعوةٌ معلَّقة قائمة تُستبدل ولا تُكدَّس: الرابط الأخير هو العامل.
        TeamInvitation::query()
            ->where('tenant_id', $tenant->id)
            ->where('email', $email)
            ->whereNull('accepted_at')
            ->delete();

        $token = Str::random(48);

        $invitation = TeamInvitation::query()->create([
            'tenant_id' => $tenant->id,
            'email' => $email,
            'role' => $role->value,
            // **مُعمّى في الجدول**: من قرأه لا ينتحل الدعوة.
            'token_hash' => hash('sha256', $token),
            'invited_by' => $actor->id,
            'expires_at' => now()->addDays(self::VALID_DAYS),
            'created_at' => now(),
        ]);

        return ['invitation' => $invitation, 'token' => $token];
    }
}
