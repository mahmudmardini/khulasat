<?php

declare(strict_types=1);

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * تنبيه كلفةٍ أو أداءٍ إلى مالك المنتج — T-22.
 *
 * **ولا قالب لارافل الافتراضي** — كـ{@see ResetPassword}: إنجليزيٌّ من
 * اليسار إلى اليمين، ورسالةٌ عربية بنصٍّ مقلوب الاتّجاه تُقرأ احتيالاً.
 */
class CostAlertTriggered extends Notification
{
    use Queueable;

    public function __construct(
        public readonly string $subject,
        public readonly string $message,
    ) {}

    /** @return array<int, string> */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject($this->subject)
            ->view('mail.cost-alert', [
                'subject' => $this->subject,
                'message' => $this->message,
                'url' => url('/admin/costs'),
            ]);
    }
}
