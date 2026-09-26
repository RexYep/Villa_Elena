<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Sent when somebody submits the sign-up form with an address that ALREADY
 * has an account.
 *
 * This mail is the whole reason the registration form can stop saying "This
 * email is already registered." The form now answers identically either way,
 * so the only place the difference appears is in the real owner's inbox —
 * which is exactly where it belongs, and the one place an attacker probing
 * addresses cannot see.
 *
 * It deliberately contains **no link that does anything**. A password-reset
 * link here would turn every enumeration attempt into a reset invitation the
 * account holder never asked for; pointing them at the normal "Forgot
 * password" page costs one extra click and cannot be weaponised. It also says
 * nothing about what the sender typed — not the name, not the phone number —
 * because none of that is trustworthy and repeating it back would make this
 * mail a delivery channel for whatever an attacker wants to write.
 */
class RegistrationAttemptMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public string $recipientName,
        public string $forgotPasswordUrl,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Someone tried to sign up with your Villa Elena email',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.registration_attempt',
        );
    }

    /**
     * @return array<int, \Illuminate\Mail\Mailables\Attachment>
     */
    public function attachments(): array
    {
        return [];
    }
}
