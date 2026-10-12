<?php

namespace App\Notifications;

use App\Support\GuestEmail;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Notifications\Messages\MailMessage;

/**
 * The email a walk-in guest gets when the front desk creates an account for
 * them and they asked for sign-in access.
 *
 * It carries an ordinary password reset link, because that is how a password
 * is chosen for an account that has none the guest knows. Until now it also
 * carried the reset email's words: "we received a password reset request for
 * your account", sent to someone who had made no request and did not yet know
 * they had an account.
 *
 * The link's lifetime is the reset link's, unchanged. The email says what to
 * do once it has run out, which the guest can always do for themselves.
 */
class WelcomeSetPasswordNotification extends ResetPassword
{
    public function toMail($notifiable): MailMessage
    {
        $resort = GuestEmail::resortName();
        $lifetime = ResetPasswordNotification::linkLifetime();

        return GuestEmail::message("Set a password for your {$resort} account", [
            'heading' => 'Your account is ready',
            'preheader' => 'Set a password to see your booking online.',
            'name' => $notifiable->first_name,
            'intro' => [
                "Our front desk created a {$resort} account for you with this email address when you booked with us.",
                'Set a password to sign in. From your account you can see your booking, pay a balance online and book again.',
            ],
            'actionLabel' => 'Set your password',
            'actionUrl' => $this->resetUrl($notifiable),
            'showUrl' => true,
            'outro' => [
                "The link works for {$lifetime}. If it has run out, open the sign-in page and choose \"Forgot password\" to get a new one: ".route('password.request'),
                'If you did not book with us or did not ask for an account, you can ignore this email. Nobody can sign in to the account until a password is set from a link sent to this address.',
            ],
            'footerNote' => 'We sent this because an account was created for this email address at our front desk.',
        ]);
    }
}
