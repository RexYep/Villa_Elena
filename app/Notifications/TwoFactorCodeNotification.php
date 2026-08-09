<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class TwoFactorCodeNotification extends Notification
{
    use Queueable;

    public function __construct(protected string $code)
    {
    }

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Your Villa Elena Login Code')
            ->greeting('New device detected')
            ->line("We noticed a login attempt from a device we don't recognize. Use the code below to verify it's you.")
            ->line("**{$this->code}**")
            ->line('This code expires in 10 minutes. If you did not try to log in, you can safely ignore this email.');
    }
}
