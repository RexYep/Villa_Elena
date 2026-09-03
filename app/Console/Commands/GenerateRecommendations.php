<?php

namespace App\Console\Commands;

use App\Services\Prescriptive\BriefingWriter;
use App\Services\Prescriptive\OutcomeTracker;
use App\Services\Prescriptive\PrescriptiveEngine;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * Araw-araw na pagtakbo ng prescriptive engine.
 *
 * Sinasadyang naka-cron ito at hindi kinokompyut tuwing bubuksan ang
 * page. Ang Insights at Forecast ay tumatawag sa Groq sa BAWAT pagbisita
 * — mabagal iyon at kumakain ng libreng quota. Ang mga mungkahi ay
 * nakatago sa database, kaya agad ang pagbukas ng page at pareho ang
 * nakikita ng admin buong araw sa halip na magbago sa bawat refresh.
 */
class GenerateRecommendations extends Command
{
    protected $signature = 'prescriptive:generate {--no-briefing : Laktawan ang AI briefing (walang tawag sa Groq)}';

    protected $description = 'Muling kompyutin ang mga prescriptive na rekomendasyon (promo at maintenance window)';

    public function handle(PrescriptiveEngine $engine, BriefingWriter $briefing, OutcomeTracker $outcomes): int
    {
        $started = microtime(true);

        try {
            $stats = $engine->run();
        } catch (\Throwable $e) {
            Log::error('prescriptive:generate failed — '.$e->getMessage());
            $this->error('Failed: '.$e->getMessage());

            return self::FAILURE;
        }

        // Isinasara ang mga lumipas nang window. Kasunod ito ng engine
        // dahil ang `run()` ang unang nagmamarka ng `expired` sa mga
        // bukas na card, kaya pare-pareho na ang estado nila bago sukatin.
        // Sariling try/catch: ang pagsukat sa nakaraan ay hindi dapat
        // pumigil sa mga mungkahi para sa hinaharap.
        try {
            $settled = $outcomes->settleDue();
            $this->line(sprintf(
                'Outcomes: %d measured, %d closed as unmeasurable.',
                $settled['settled'],
                $settled['unmeasurable']
            ));
        } catch (\Throwable $e) {
            Log::error('Outcome settling failed — '.$e->getMessage());
            $this->warn('Outcome settling failed: '.$e->getMessage());
        }

        // Pagkatapos ng mga numero, hindi kasabay. Ang briefing ay
        // palamuti — kung bumagsak ang Groq, kompleto pa rin ang tunay na
        // produkto, at hindi dapat maging FAILURE ang buong command dahil
        // doon. Ang `--no-briefing` ay para sa mga pagtakbong ayaw gumasta
        // ng tawag sa API (hal. paulit-ulit na pagsubok habang nagde-develop).
        if (! $this->option('no-briefing')) {
            $text = $briefing->write();
            $this->line($text ? 'Briefing written.' : 'Briefing skipped or unavailable.');
        }

        $elapsed = round(microtime(true) - $started, 2);

        $this->info(sprintf(
            'Recommendations: %d new, %d refreshed, %d untouched, %d expired (%ss)',
            $stats['created'],
            $stats['refreshed'],
            $stats['skipped'],
            $stats['expired'],
            $elapsed
        ));

        return self::SUCCESS;
    }
}
