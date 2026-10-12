<?php

namespace App\Notifications;

use App\Support\GuestEmail;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Notifications\Messages\MailMessage;

/**
 * The email a guest gets after asking for a password reset.
 *
 * Laravel's class still makes the link; only the words and the template are
 * ours. "You can ignore this email" is correct HERE, and only here: anyone
 * can type an address into the Forgot password form, so an unexpected reset
 * email does not mean the account is at risk. The sign-in code email is the
 * opposite case and must not borrow this sentence.
 */
class ResetPasswordNotification extends ResetPassword
{
    public function toMail($notifiable): MailMessage
    {
        $resort = GuestEmail::resortName();

        return GuestEmail::message("Reset your {$resort} password", [
            'heading' => 'Reset your password',
            'preheader' => 'Choose a new password. The link works for '.self::linkLifetime().'.',
            'name' => $notifiable->first_name,
            'intro' => [
                "We received a request to reset the password for your {$resort} account. Choose a new one with the button below.",
            ],
            'actionLabel' => 'Choose a new password',
            'actionUrl' => $this->resetUrl($notifiable),
            'showUrl' => true,
            'outro' => [
                'The link works for '.self::linkLifetime().' and can be used once.',
                'If you did not ask for this, you can ignore this email. Your password stays the same.',
            ],
            'footerNote' => 'We sent this because a password reset was requested for this email address.',
        ]);
    }

    /**
     * "60 minutes", read from the same setting the broker enforces, so the
     * email cannot promise a lifetime the link does not have.
     */
    public static function linkLifetime(): string
    {
        $minutes = (int) config('auth.passwords.'.config('auth.defaults.passwords').'.expire', 60);

        return $minutes.' '.($minutes === 1 ? 'minute' : 'minutes');
    }
}
