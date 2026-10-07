<?php

namespace App\Console\Commands;

use App\Services\Prescriptive\BriefingWriter;
use App\Services\Prescriptive\PrescriptiveEngine;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * The daily run of the recommendation rules.
 *
 * Scheduled on purpose rather than worked out on every page view: the cards
 * are stored, so the page opens at once and the admin sees the same list
 * all day instead of one that shifts on each refresh.
 */
class GenerateRecommendations extends Command
{
    protected $signature = 'prescriptive:generate {--no-briefing : Skip the AI summary (no AI call)}';

    protected $description = 'Re-run the recommendation rules (promo, peak pricing, maintenance, turnover cleaning, balance reminders)';

    public function handle(PrescriptiveEngine $engine, BriefingWriter $briefing): int
    {
        $started = microtime(true);

        try {
            $stats = $engine->run();
        } catch (\Throwable $e) {
            Log::error('prescriptive:generate failed — '.$e->getMessage());
            $this->error('Failed: '.$e->getMessage());

            return self::FAILURE;
        }

        // After the cards, never alongside them. The summary is decoration —
        // if the AI is down the real product is still complete, and that
        // must not turn the whole command into a FAILURE. `--no-briefing` is
        // for runs that should not spend an API call (repeated testing).
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
