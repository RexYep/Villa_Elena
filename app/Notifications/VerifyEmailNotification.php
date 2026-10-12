<?php

namespace App\Notifications;

use App\Console\Commands\PruneUnverifiedUsers;
use App\Support\GuestEmail;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Notifications\Messages\MailMessage;

/**
 * The email that asks a new guest to confirm their address.
 *
 * Laravel's class still signs the link. The stock wording left out the two
 * things a guest trips over: the link runs out, and it only completes while
 * signed in as the account it is for (AuthController::verifyEmail()), so a
 * guest who opens it signed out is taken to the sign-in page first.
 */
class VerifyEmailNotification extends VerifyEmail
{
    public function toMail($notifiable): MailMessage
    {
        $resort = GuestEmail::resortName();
        $minutes = (int) config('auth.verification.expire', 60);
        $lifetime = $minutes.' '.($minutes === 1 ? 'minute' : 'minutes');

        return GuestEmail::message("Confirm your email for {$resort}", [
            'heading' => 'Confirm your email address',
            'preheader' => 'One step left before you can book.',
            'name' => $notifiable->first_name,
            'intro' => [
                "Thanks for signing up with {$resort}. Confirm this email address to finish setting up your account. You will need to do this before you can book.",
            ],
            'actionLabel' => 'Confirm email address',
            'actionUrl' => $this->verificationUrl($notifiable),
            'showUrl' => true,
            'outro' => [
                "The link works for {$lifetime}. If you are not signed in when you open it, you will be asked to sign in first with the email and password you chose.",
                'If the link has run out, sign in and you can ask for a new one.',
                'If you did not create an account, you can ignore this email. An account that is never confirmed is removed after '.PruneUnverifiedUsers::DEFAULT_HOURS.' hours.',
            ],
            'footerNote' => 'We sent this because this email address was used to sign up.',
        ]);
    }
}
