<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;

// Ang pagpapalit ng mailer ay dating tahimik na nabibigo: walang
// email, walang error sa screen, at ang tanging bakas ay nasa
// laravel.log — dahil naka-try/catch ang lahat ng call site (sinadya
// iyon: hindi dapat mag-500 ang booking dahil sa email). Ipinapakita
// ng command na ito ang aktwal na configuration at ang totoong
// exception, para makita agad kung bakit hindi umaandar.
class TestMail extends Command
{
    protected $signature = 'mail:test {email : Saan ipapadala ang test message}';

    protected $description = 'Send a test email using the currently configured mailer, and report exactly what failed if it does not go out';

    public function handle(): int
    {
        $mailer = config('mail.default');
        $from = config('mail.from.address');

        $this->newLine();
        $this->line('  <fg=gray>Mailer</>          '.$mailer);
        $this->line('  <fg=gray>From</>            '.($from ?: '<not set>'));

        if ($mailer === 'smtp') {
            $this->line('  <fg=gray>Host</>            '.config('mail.mailers.smtp.host').':'.config('mail.mailers.smtp.port'));
            $this->line('  <fg=gray>Username</>        '.(config('mail.mailers.smtp.username') ?: '<not set>'));
            $this->line('  <fg=gray>Password</>        '.(config('mail.mailers.smtp.password') ? 'set' : '<not set>'));
        }

        if ($mailer === 'brevo') {
            $dsn = config('services.brevo.dsn');
            $this->line('  <fg=gray>MAILER_DSN</>      '.($dsn ? $this->maskedDsn($dsn) : '<not set>'));
            $this->newLine();
            $this->warn('  Brevo is the production transport (Render blocks outbound SMTP).');
            $this->line('  <fg=gray>Local dev normally uses MAIL_MAILER=smtp.</>');

            // Madalas na pagkakamali: ang SMTP key ang nailalagay sa
            // DSN. Tinatanggap ito ng Brevo hanggang sa mag-401 —
            // walang email, at hindi sinasabi ng mensahe kung bakit.
            // Mas mabuti nang sabihin dito bago pa magpadala.
            if ($dsn && str_contains($dsn, 'xsmtpsib-')) {
                $this->newLine();
                $this->error('  MAILER_DSN contains an SMTP key ("xsmtpsib-..."), not an API v3 key.');
                $this->line('  The HTTPS API needs a key starting with "xkeysib-" (Brevo → SMTP & API → API keys).');
                $this->line('  An SMTP key belongs with MAIL_MAILER=smtp instead.');

                return self::FAILURE;
            }
        }

        if (blank($from)) {
            $this->newLine();
            $this->error('  MAIL_FROM_ADDRESS is empty — the send will be rejected before it leaves.');

            return self::FAILURE;
        }

        $to = $this->argument('email');
        $this->newLine();
        $this->line("  Sending to <options=bold>{$to}</>...");

        try {
            Mail::raw(
                "This is a test message from Villa Elena Resort.\n\n"
                ."Mailer: {$mailer}\nSent:   ".now()->toDayDateTimeString()."\n\n"
                .'If you received this, outbound mail is working.',
                fn ($message) => $message->to($to)->subject('Villa Elena — mail test ('.$mailer.')')
            );
        } catch (\Throwable $e) {
            $this->newLine();
            $this->error('  Send failed: '.$e->getMessage());
            $this->newLine();
            $this->line('  <fg=gray>'.$e::class.'</>');

            return self::FAILURE;
        }

        $this->newLine();
        $this->info("  Sent via {$mailer}. Check the inbox (and the spam folder).");

        if ($mailer === 'brevo') {
            $this->line('  <fg=gray>Brevo accepts the API call before delivering. If nothing arrives,</>');
            $this->line("  <fg=gray>check that {$from} is a verified sender in your Brevo account.</>");
        }

        return self::SUCCESS;
    }

    // Ipinapakita nang sapat para makilala, pero hindi buo — lumalabas
    // ito sa terminal at sa mga screenshot.
    private function maskedDsn(string $dsn): string
    {
        return preg_replace('#://[^@]+@#', '://'.str_repeat('*', 8).'@', $dsn);
    }
}
