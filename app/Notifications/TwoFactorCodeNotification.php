<?php

namespace App\Notifications;

use App\Support\GuestEmail;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * The six-digit code for signing in on a device we have not seen.
 *
 * A code is only ever issued after the correct password was entered: both
 * senders (AuthController::login() and resendTwoFactor()) sit behind that
 * check. So a guest who receives one without having tried to sign in is
 * looking at proof that somebody else knows their password. This email used
 * to tell them "you can safely ignore this email" — the one piece of advice
 * that is wrong here. It now says what it means and where to change the
 * password. Do not soften it back.
 */
class TwoFactorCodeNotification extends Notification
{
    use Queueable;

    public function __construct(
        protected string $code,
        // How long the code works, from the constant that enforces it
        // (AuthController::OTP_TTL_MINUTES), so the two cannot disagree.
        protected int $expiresInMinutes = 10,
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $resort = GuestEmail::resortName();
        $minutes = $this->expiresInMinutes.' '.($this->expiresInMinutes === 1 ? 'minute' : 'minutes');

        return GuestEmail::message("Your login code for {$resort}", [
            'heading' => 'Your login code',
            'preheader' => "It works for {$minutes}. Do not share it with anyone.",
            'name' => $notifiable->first_name,
            'intro' => [
                "Enter this code to finish signing in to your {$resort} account:",
            ],
            'code' => $this->code,
            'codeNote' => "Works for {$minutes}, and only once.",
            'outro' => [
                'We ask for a code when you sign in on a device or browser we have not seen you use before.',
            ],
            'warning' => 'Was this not you? Then someone else has entered your password. They cannot get in without this code, so do not share it with anyone, and change your password now.',
            'warningLinkLabel' => 'Reset your password',
            'warningLinkUrl' => route('password.request'),
            'footerNote' => 'We sent this because your password was entered on a device we did not recognise. Login codes are always sent while two-factor login is on for your account.',
        ]);
    }
}
