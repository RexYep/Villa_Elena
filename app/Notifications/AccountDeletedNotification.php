<?php

namespace App\Notifications;

use App\Support\GuestEmail;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * The last email sent to a deleted account's address (AccountErasure).
 *
 * Sent through `Notification::route()`, not `$user->notify()`: by the time it
 * goes out the account has no email, so the address and the name are handed
 * in. It says what was kept as well as what was removed, because "deleted"
 * alone would be read as "every record of me is gone", and that is not what
 * the law allows.
 */
class AccountDeletedNotification extends Notification
{
    use Queueable;

    public function __construct(
        protected string $firstName,
        protected bool $byResort = false,
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $resort = GuestEmail::resortName();
        $contact = GuestEmail::contact();
        $reach = $contact['phone'] ? " Call us at {$contact['phone']}." : '';

        return GuestEmail::message("Your account has been deleted — {$resort}", [
            'heading' => 'Your account has been deleted',
            'preheader' => 'Your name, email, phone, address and photo have been removed.',
            'name' => $this->firstName,
            'intro' => [
                ($this->byResort ? 'As you asked, we have deleted your ' : 'Your ')
                    ."{$resort} account".($this->byResort ? '.' : ' has been deleted.')
                    .' Your name, email address, phone number, address, profile photo and sign-in history have been removed.',
                'Records of past bookings and payments are kept without your name, because the law requires us to keep proof of every transaction. A review you left stays up under "'.\App\Models\User::ANONYMIZED_NAME.'".',
            ],
            'outro' => [
                'You can create a new account with this email address at any time. It will start fresh, without your past bookings.',
            ],
            'warning' => $this->byResort ? null : "Did you not delete your account? Contact us right away.{$reach}",
            'footerNote' => 'This is the last email we will send to this address about this account.',
        ]);
    }
}
