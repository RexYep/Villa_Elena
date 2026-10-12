<?php

namespace App\Mail;

use App\Support\GuestEmail;
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
 * password" page costs one extra click and cannot be weaponised. That is
 * still true now that the link is a button: it opens the same public page.
 * It also says nothing about what the sender typed — not the name, not the
 * phone number — because none of that is trustworthy and repeating it back
 * would make this mail a delivery channel for whatever an attacker wants to
 * write.
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
        // The resort's name comes last in every subject: it is long, and a
        // phone's inbox cuts a subject short from the right.
        return new Envelope(
            from: GuestEmail::sender(),
            subject: 'Someone tried to sign up with your email at '.GuestEmail::resortName(),
        );
    }

    public function content(): Content
    {
        $resort = GuestEmail::resortName();

        return new Content(
            view: 'emails.message',
            text: 'emails.message_text',
            with: [
                'heading' => 'Someone tried to sign up with your email',
                'preheader' => 'Nothing about your account has changed.',
                'name' => $this->recipientName,
                'intro' => [
                    "Someone just tried to create a {$resort} account using this email address. You already have one, so nothing was created and nothing about your account has changed.",
                    'If this was you, sign in as usual. If you have forgotten your password, you can ask for a reset link here:',
                ],
                'actionLabel' => 'Go to Forgot password',
                'actionUrl' => $this->forgotPasswordUrl,
                'outro' => [
                    'If this was not you, there is nothing you need to do. Somebody mistyped their address, or was checking whether you have an account here. Your password still works and no one has gained access to it.',
                ],
                'footerNote' => 'We sent this once, to the address that was entered. We will not email you again about further attempts today.',
            ],
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
