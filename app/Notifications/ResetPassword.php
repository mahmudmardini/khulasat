<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Support\Arabic;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * رسالة استعادة كلمة المرور — SCREENS.md §1، والمهمّة T-32.
 *
 * **ولا قالب لارافل الافتراضي**: إنجليزيٌّ من اليسار إلى اليمين، ورسالةٌ
 * تصل جهةً عربية بنصٍّ إنجليزيّ مقلوب الاتّجاه **تُقرأ رسالةَ احتيال** —
 * فتُحذف، ويبقى صاحبها خارج حسابه.
 */
class ResetPassword extends Notification
{
    use Queueable;

    public function __construct(private readonly string $token) {}

    /** @return array<int, string> */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $minutes = (int) config('auth.passwords.users.expire', 60);

        return (new MailMessage)
            ->subject(trans('auth.reset.mail_subject'))
            /*
             * **والبريد يُمرَّر في الرابط مع الرمز.** فالرمز وحده لا يكفي
             * وسيطَ لارافل للتحقّق: يبحث بالبريد ثمّ يوازن الرمز.
             */
            ->view('mail.reset-password', [
                'url' => route('password.reset', ['token' => $this->token]).'?email='.urlencode((string) $notifiable->getEmailForPasswordReset()),
                'name' => (string) ($notifiable->name ?? ''),
                /*
                 * **عربية هندية**: رقمٌ في جملةٍ نثرية — SCREENS.md §الخطوط.
                 * واللاتينيّ للجداول والحقول «لأنها تُقارن وتُحاذى»، ورسالةُ
                 * بريدٍ ليست منهما.
                 */
                'minutes' => Arabic::toArabicIndicDigits($minutes),
            ]);
    }
}
