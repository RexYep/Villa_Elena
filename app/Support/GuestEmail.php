<?php

namespace App\Support;

use App\Models\Setting;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Notifications\Messages\MailMessage;

/**
 * What every email to a guest shares: the resort's name, how to reach it,
 * and the one template the short account emails are written in.
 *
 * Before this, each email typed the resort's name for itself and four
 * spellings were in use, and "contact us" appeared with no way to do it
 * although the phone, address and map link were all in Settings.
 */
class GuestEmail
{
    /**
     * The name printed in an email: the "Resort Name" setting, the same one
     * the site's own pages show.
     */
    public static function resortName(): string
    {
        return Setting::resortName();
    }

    /**
     * Who a guest email is from: the configured address, under the same name
     * the email is signed with. Without this the sender name is
     * `MAIL_FROM_NAME`, and an email signed with a different name from the
     * one it was sent under reads as a different sender.
     */
    public static function sender(): Address
    {
        return new Address((string) config('mail.from.address'), self::resortName());
    }

    /**
     * The contact lines for the footer. A blank setting is left out rather
     * than printed as an empty line.
     *
     * @return array{name: string, phone: ?string, email: ?string, address: ?string, map_url: ?string}
     */
    public static function contact(): array
    {
        $value = fn (string $key) => trim((string) Setting::get($key)) ?: null;

        return [
            'name' => self::resortName(),
            'phone' => $value('resort_phone'),
            'email' => $value('resort_email'),
            'address' => $value('resort_address'),
            'map_url' => $value('google_maps_url'),
        ];
    }

    /**
     * An absolute link for an email, built from APP_URL.
     *
     * Not from the current request's host: a booking email is sent from the
     * PayMongo webhook and from the admin's own session, and neither is
     * necessarily an address the guest can open.
     */
    public static function url(string $relativePath): string
    {
        return rtrim((string) config('app.url'), '/').'/'.ltrim($relativePath, '/');
    }

    /**
     * A short account email (sign-in code, password, verification) in the
     * shared template, with a plain-text copy.
     *
     * `$data` is what `emails/message.blade.php` reads: `heading`,
     * `preheader`, `name`, `intro` (paragraphs), `code` + `codeNote`,
     * `actionLabel` + `actionUrl`, `showUrl`, `outro` (paragraphs),
     * `warning` + `warningLinkLabel` + `warningLinkUrl`, `footerNote`.
     */
    public static function message(string $subject, array $data): MailMessage
    {
        $sender = self::sender();

        return (new MailMessage)
            ->from($sender->address, $sender->name)
            ->subject($subject)
            ->view(['emails.message', 'emails.message_text'], $data);
    }
}
