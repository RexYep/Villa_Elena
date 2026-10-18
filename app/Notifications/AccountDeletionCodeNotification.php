<?php

namespace App\Notifications;

use App\Support\AccountDeletionCode;
use App\Support\GuestEmail;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * The code that confirms deleting an account, for a guest with two-factor
 * login on (see AccountDeletionCode).
 *
 * It is only sent from inside a signed-in session, so a guest who receives
 * one without asking is looking at proof that someone else is signed in to
 * their account. Like the sign-in code email, it must not say "you can
 * ignore this".
 */
class AccountDeletionCodeNotification extends Notification
{
    use Queueable;

    public function __construct(protected string $code) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $resort = GuestEmail::resortName();
        $minutes = AccountDeletionCode::TTL_MINUTES.' minutes';

        return GuestEmail::message("Code to delete your account — {$resort}", [
            'heading' => 'Confirm deleting your account',
            'preheader' => "It works for {$minutes}. Do not share it with anyone.",
            'name' => $notifiable->first_name,
            'intro' => [
                "Enter this code on your profile page to delete your {$resort} account:",
            ],
            'code' => $this->code,
            'codeNote' => "Works for {$minutes}, and only once.",
            'outro' => [
                'Deleting your account cannot be undone.',
            ],
            'warning' => 'Did you not ask for this? Then someone else is signed in to your account. Do not share this code, and change your password now.',
            'warningLinkLabel' => 'Reset your password',
            'warningLinkUrl' => route('password.request'),
            'footerNote' => 'We sent this because "Delete my account" was started on your profile page while you were signed in.',
        ]);
    }
}
