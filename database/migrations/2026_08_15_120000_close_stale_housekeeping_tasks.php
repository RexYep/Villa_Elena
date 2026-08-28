<?php

use App\Models\HousekeepingTask;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Isinasara ang naipong housekeeping backlog.
     *
     * Walang nagsasara ng loop dati: ginagawa ng check-in ang
     * `checkout_clean` na task, binabaligtad ito ng auto-checkout
     * papuntang `in_progress` — pero manu-mano ang pagmarka ng
     * "Complete", at kailangan pang bisitahin ang isang tab para
     * doon. Resulta: 11 sa 13 na bukas na task ay overdue, may umabot
     * ng 48 araw, at 6 ang naipit sa `in_progress`.
     *
     * Sinasara nito ang mga task na wala nang saysay pang gawin:
     *   1. Lampas 7 araw na ang due_date — kung hindi pa nalinis
     *      ngayon, matagal nang nadaanan ng susunod na bisita.
     *   2. Ang kaugnay na booking ay nag-checkout na nang mahigit 3
     *      araw — tapos na ang turnaround window nito.
     *
     * Minamarkahan silang `completed` (wala namang ibang value ang
     * enum), pero may malinaw na nota kung bakit — para hindi ito
     * magmukhang totoong naitalang paglilinis.
     */
    public function up(): void
    {
        $note = ' [Auto-closed: stale backlog cleared during the housekeeping fix — '
              . 'this was never marked done through the frontdesk.]';

        HousekeepingTask::whereIn('status', ['pending', 'in_progress'])
            ->whereDate('due_date', '<', today()->subDays(7))
            ->get()
            ->each(function ($task) use ($note) {
                $task->update([
                    'status'       => 'completed',
                    'completed_at' => now(),
                    'notes'        => trim(($task->notes ?? '') . $note),
                ]);
            });

        HousekeepingTask::whereIn('status', ['pending', 'in_progress'])
            ->with('booking')
            ->get()
            ->filter(fn ($task) => $task->booking
                && $task->booking->checkOutDateTime()->lt(now()->subDays(3)))
            ->each(function ($task) use ($note) {
                $task->update([
                    'status'       => 'completed',
                    'completed_at' => now(),
                    'notes'        => trim(($task->notes ?? '') . $note),
                ]);
            });
    }

    /**
     * Hindi maibabalik — hindi na matutukoy kung alin sa mga
     * `completed` na task ang isinara dito at alin ang tunay na
     * natapos ng staff.
     */
    public function down(): void
    {
        //
    }
};
