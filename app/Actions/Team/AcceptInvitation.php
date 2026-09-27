<?php

declare(strict_types=1);

namespace App\Actions\Team;

use App\Models\TeamInvitation;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * يقبل الدعوة فيُنشئ العضو — SCREENS.md §10، والمهمّة T-33.
 *
 * **ويجري بلا جلسة**: المدعوّ ليس في الجهة بعد، فسياقُ المستأجر فارغ.
 * ولذلك يُقرأ الصفُّ بـ`acrossTenants` صراحةً، **ويُكتب المستخدم بـ
 * `tenant_id` الدعوة لا بسياقٍ لا وجود له**.
 */
final class AcceptInvitation
{
    /** @return array{user: User, invitation: TeamInvitation} */
    public function handle(string $token, string $name, string $password): array
    {
        $invitation = TeamInvitation::acrossTenants()
            // **مقارنةُ تعميةٍ لا رمزٍ خامّ** — الجدول لا يحمل الرمز أصلاً.
            ->where('token_hash', hash('sha256', $token))
            ->first();

        if ($invitation === null || ! $invitation->isPending()) {
            throw new RuntimeException(trans('team.errors.invalid_invitation'));
        }

        // وبين إرسال الدعوة وقبولها قد يُنشأ الحساب من طريقٍ آخر.
        if (User::acrossTenants()->where('email', $invitation->email)->exists()) {
            throw new RuntimeException(trans('team.errors.already_registered'));
        }

        return DB::transaction(function () use ($invitation, $name, $password): array {
            $user = new User;

            $user->forceFill([
                'tenant_id' => $invitation->tenant_id,
                'name' => $name,
                'email' => $invitation->email,
                'password' => $password,
                'role' => $invitation->role->value,
            ])->save();

            /*
             * **وتُختم لا تُحذف.** فمن دعا يريد أن يعرف أنّ دعوته قُبلت ومتى،
             * وحذفُها يمحو ذلك ويجعل العضو يظهر في الفريق بلا أصل.
             */
            $invitation->forceFill(['accepted_at' => now()])->save();

            return ['user' => $user, 'invitation' => $invitation];
        });
    }
}
