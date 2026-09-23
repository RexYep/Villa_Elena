<?php

namespace App\Console\Commands;

use App\Services\CacheDiagnostics;
use Illuminate\Console\Command;

/**
 * Prints CacheDiagnostics as tables. The measurements themselves live in the
 * service, so this and the production diagnostics route can't drift apart —
 * the whole point is comparing the same numbers across the two environments.
 */
class CacheBenchmark extends Command
{
    protected $signature = 'cache:benchmark
                            {--iterations=100 : How many times to repeat each timing}';

    protected $description = 'Show what the cache layer saves: queries eliminated, and per-store read cost';

    public function handle(): int
    {
        $report = CacheDiagnostics::measure((int) $this->option('iterations'));

        $this->newLine();
        $this->line('  <fg=cyan;options=bold>Villa Elena — cache benchmark</>');
        $this->line('  <fg=gray>env: '.$report['environment']['app_env']
            .'  ·  cache store: '.$report['environment']['cache_store']
            .'  ·  client: '.$report['environment']['redis_client'].'</>');
        $this->newLine();

        if (! $report['redis']['reachable']) {
            $this->line('  <fg=red>✘</> Redis is NOT reachable: '.$report['redis']['error']);
            $this->line('    Start it with: <fg=yellow>docker compose up -d redis</>');
            $this->newLine();

            return self::FAILURE;
        }

        $this->line('  <fg=green>✔</> Redis reachable — server v'.$report['redis']['version']);
        $this->newLine();

        $this->renderQueriesEliminated($report['queries_eliminated']);
        $this->renderReadCost($report['read_cost_ms'], $report['iterations']);
        $this->renderTransport($report['transport']);

        return self::SUCCESS;
    }

    private function renderQueriesEliminated(array $queries): void
    {
        $this->line('  <options=bold>Database queries eliminated</> <fg=gray>— identical in every environment</>');

        $this->table(['Operation', 'Without cache', 'With cache (warm)'], [
            [
                'Landing page settings ('.CacheDiagnostics::LANDING_PAGE_SETTING_READS.' reads)',
                $queries['landing_page_settings']['without_cache'],
                $queries['landing_page_settings']['with_cache'],
            ],
            [
                'Admin dashboard stats (per load)',
                $queries['admin_dashboard_stats']['without_cache'],
                $queries['admin_dashboard_stats']['with_cache'],
            ],
        ]);
    }

    private function renderReadCost(array $costs, int $iterations): void
    {
        $this->line('  <options=bold>Cost of one cache read</> <fg=gray>— '.$iterations.' iterations, this machine only</>');

        $rows = [['No cache (MySQL query)', $this->ms($costs['no_cache_mysql_query'])]];

        foreach (CacheDiagnostics::STORES as $store) {
            $rows[] = [$this->storeLabel($store), $this->ms($costs[$store] ?? null)];
        }

        $this->table(['Backing store', 'Per read'], $rows);
    }

    /**
     * The panel will ask why Redis is the slowest row on a dev machine.
     * Measure the answer rather than asserting it: a PING does no work, so
     * whatever it costs is pure transport.
     */
    private function renderTransport(array $transport): void
    {
        $this->line('  <options=bold>Transport cost</> <fg=gray>— why the ranking differs between environments</>');
        $this->line('    Redis PING  (does no work) ...... '.$this->ms($transport['redis_ping_ms']));
        $this->line('    MySQL SELECT 1 ................. '.$this->ms($transport['mysql_select_1_ms']));
        $this->newLine();
        $this->line('  <fg=gray>Whichever of those two is cheaper is the nearer service, and that</>');
        $this->line('  <fg=gray>decides the ranking above. Run the same measurement in production</>');
        $this->line('  <fg=gray>(GET /diagnostics/cache/{CRON_SECRET}) to compare — the local result</>');
        $this->line('  <fg=gray>says nothing about Render, where the database is the remote one.</>');
        $this->newLine();
    }

    private function ms(?float $milliseconds): string
    {
        return $milliseconds === null ? 'unavailable' : number_format($milliseconds, 3).' ms';
    }

    private function storeLabel(string $store): string
    {
        return match ($store) {
            'redis' => 'Redis',
            'database' => 'MySQL cache table (the failover)',
            'file' => 'Filesystem cache',
            default => ucfirst($store),
        };
    }
}
