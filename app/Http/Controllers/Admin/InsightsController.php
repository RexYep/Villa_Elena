<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Concerns\ThrottlesAiRefresh;
use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\Payment;
use App\Models\Setting;
use App\Services\AiReportStore;
use App\Services\GeminiService;
use App\Services\Insights\MonthToDate;

/**
 * /admin/insights — what has happened this month so far (v7.83).
 *
 * The FIGURES are worked out by `MonthToDate`, by rule, on every visit. The
 * AI only turns them into five sentences, one per topic, and is told not to
 * add a number of its own. Until v7.83 the AI was handed ten bare counts and
 * read all-time totals as this month's (see MonthToDate's docblock).
 *
 * Three pages, three questions, and they must not overlap:
 *   Insights         what happened (this month so far)
 *   Forecast         what is likely to happen (the next months)
 *   Recommendations  what to do about it
 * So this prompt asks for neither a prediction nor advice.
 */
class InsightsController extends Controller
{
    use ThrottlesAiRefresh;

    /**
     * Bumped when the prompt's inputs change shape. A stored report from an
     * older version was written from figures this page no longer shows, so it
     * is treated as no report at all.
     */
    private const REPORT_VERSION = 2;

    /** One sentence per topic, in this order. The prompt and the check below both read it. */
    private const TOPICS = [
        'bookings made',
        'cancellations',
        'slots booked for stays',
        'money received',
        'guests',
    ];

    /** Set by ask() when the AI answered but not in the form asked for. */
    private bool $answerRejected = false;

    /**
     * Reads the stored insights; calls the AI only when there are none yet
     * (or only ones from an older version). The figures are live either way.
     */
    public function index(AiReportStore $store, GeminiService $ai, MonthToDate $facts)
    {
        $data = $facts->build();
        $report = $store->read(AiReportStore::INSIGHTS);

        if (! $this->isCurrentVersion($report)) {
            $report = $store->refresh(
                AiReportStore::INSIGHTS,
                fn () => $this->ask($ai, $data),
                $this->reportMeta($data)
            );
        }

        return $this->render($data, $report);
    }

    /**
     * The Refresh button. The ONLY path that spends an AI request on purpose.
     */
    public function refresh(AiReportStore $store, GeminiService $ai, MonthToDate $facts)
    {
        if ($wait = $this->aiRefreshCooldown(AiReportStore::INSIGHTS)) {
            return back()->with('error', $this->aiRefreshCooldownMessage('insights summary', $wait));
        }

        $data = $facts->build();

        // NULL means "not rewritten" — `refresh()` leaves the previous text
        // in place.
        $refreshed = $store->refresh(
            AiReportStore::INSIGHTS,
            fn () => $this->ask($ai, $data),
            $this->reportMeta($data)
        );

        if ($refreshed !== null) {
            return back()->with('success', 'Insights rewritten from the current figures.');
        }

        $reassurance = 'The figures above are worked out without the AI and are unaffected.';

        return back()->with('error', $this->answerRejected
            ? 'The insights could not be rewritten — the AI answered, but not in five lines as asked. Please try again. '.$reassurance
            : $this->aiFailureMessage($ai, 'The insights could not be rewritten', $reassurance));
    }

    /**
     * The AI's five lines, cleaned, or NULL when it did not answer or did not
     * answer in five lines. A wrong-shaped answer is not stored: five boxes
     * are drawn from it, and a sixth line is usually a preamble or a guess.
     */
    private function ask(GeminiService $ai, array $data): ?string
    {
        $this->answerRejected = false;

        // 1024: five sentences, with room for hidden reasoning, which is
        // billed against the same cap. 0.3: it restates given figures, and
        // warmth only adds invented ones (the Forecast measurement).
        $text = $ai->ask($this->prompt($data), 1024, 0.3);

        if ($text === null) {
            return null;
        }

        // `/u` on both: without it `\R` also matches the byte 0x85, which is
        // the last byte of 📅, and the split cut that emoji in half.
        $lines = collect(preg_split('/\R/u', $text) ?: [])
            // Numbering, bullets and bold are asked against, and stripped
            // anyway: the view prints each line as plain text.
            ->map(fn ($line) => trim(str_replace(['**', '__'], '', preg_replace('/^\s*(?:[-*•]|\d+[.)])\s+/u', '', $line) ?? $line)))
            ->filter(fn ($line) => $line !== '')
            ->values();

        if ($lines->count() !== count(self::TOPICS)) {
            $this->answerRejected = true;

            return null;
        }

        return $lines->implode("\n");
    }

    /**
     * @param  array{text: string, generated_at: \Carbon\Carbon, meta: ?array}|null  $report
     */
    private function isCurrentVersion(?array $report): bool
    {
        return $report !== null && ($report['meta']['version'] ?? null) === self::REPORT_VERSION;
    }

    /**
     * What the text was written from, kept with it so the page can say when
     * it no longer matches the live figures.
     *
     * @return array<string, mixed>
     */
    private function reportMeta(array $data): array
    {
        return [
            'version' => self::REPORT_VERSION,
            'month' => $data['key'],
            'as_of' => $data['today']->toDateString(),
            'figures' => $this->figures($data),
        ];
    }

    /**
     * The figures the five lines quote, for both periods. Anything here that
     * moves makes the stored text out of date.
     *
     * @return array<string, array<int, int|string>>
     */
    private function figures(array $data): array
    {
        $figures = [];

        foreach (['current', 'previous'] as $side) {
            $p = $data[$side];

            $figures[$side] = [
                $p['label'],
                $p['made'], $p['standing'], $p['cancelled'],
                ...array_values($p['cancelled_by']),
                $p['slots_booked'], $p['slots_offered'],
                (int) round($p['net']),
                $p['stays']['total'], $p['stays']['returning'],
                $p['new_accounts'],
            ];
        }

        return $figures;
    }

    /**
     * @param  array{text: string, generated_at: \Carbon\Carbon, meta: ?array}|null  $report
     */
    private function render(array $data, ?array $report)
    {
        // Text from before v7.83 reads all-time totals as this month's;
        // showing it beside the table would put two answers on one page.
        if (! $this->isCurrentVersion($report)) {
            $report = null;
        }

        $reportState = null;
        $writtenFor = null;

        if ($report !== null) {
            $meta = $report['meta'];

            // Most specific first: a new month means a different period
            // entirely; a later day means the period grew; otherwise the
            // same day's bookings or payments moved.
            $reportState = match (true) {
                ($meta['month'] ?? null) !== $data['key'] => 'month_moved',
                ($meta['as_of'] ?? null) !== $data['today']->toDateString() => 'days_moved',
                ($meta['figures'] ?? null) !== $this->figures($data) => 'figures_changed',
                default => 'current',
            };

            $writtenFor = $meta['figures']['current'][0] ?? null;
        }

        return view('admin.insights.index', [
            'insights' => $data,
            'lines' => $report === null ? null : explode("\n", $report['text']),
            'reportState' => $reportState,
            'writtenFor' => $writtenFor,
            'generatedAt' => $report['generated_at'] ?? null,
        ]);
    }

    /**
     * The facts the AI may use — and nothing else (the v7.20 grounding rule:
     * the prompt is the complete extent of what it knows, and a denial is a
     * claim too). Every figure is printed already formatted, and every
     * comparison already made, so the model copies instead of working out.
     */
    private function prompt(array $data): string
    {
        $now = $data['current'];
        $was = $data['previous'];

        $resort = Setting::resortName();
        $today = $data['today']->format('l, F j, Y');

        $slots = collect(Booking::SLOTS)
            ->map(fn ($s) => $s['name'].' ('.$s['times'].')')
            ->implode(', ');

        $cancelledBy = function (array $p): string {
            $parts = [];

            foreach (MonthToDate::CANCELLED_BY as $key => $label) {
                if ($p['cancelled_by'][$key] > 0) {
                    $parts[] = strtolower($label).': '.$p['cancelled_by'][$key];
                }
            }

            return $parts === [] ? 'none' : implode('; ', $parts);
        };

        $list = fn (array $rows) => $rows === []
            ? 'none'
            : collect($rows)->map(fn ($r) => $r['label'].' '.$r['count'])->implode(', ');

        $pct = fn (?float $v) => $v === null ? 'no slots were for sale' : number_format($v, 0).'%';

        // The single fact the "guests" line must not get wrong: a returning
        // guest is one with an earlier finished stay, and nothing else.
        $guestLine = fn (array $p) => $p['stays']['total'] === 0
            ? 'no stays with a check-in date in this period'
            : "{$p['stays']['total']} stays with a check-in date in this period; {$p['stays']['returning']} by guests who had stayed before, {$p['stays']['first']} by first-time guests";

        $villaNow = $data['now']['checked_in'] ? 'a guest is checked in' : 'no guest is checked in';
        $topics = collect(self::TOPICS)->map(fn ($t, $i) => ($i + 1).'. '.$t)->implode("\n");

        return <<<PROMPT
You are writing a short summary for the owner of {$resort}, a single private villa in the Philippines that is rented whole to one guest group at a time.

The figures have ALREADY BEEN CALCULATED by the system. Your only job is to put them into plain, formal sentences.

Today is {$today}. THIS MONTH SO FAR is {$now['label']} ({$now['days']} days). It is compared with THE SAME DAYS LAST MONTH, {$was['label']} — not with the whole of last month.

WHAT A SLOT IS: one stay the villa can sell. The slots are: {$slots}. An ordinary date offers Day and Night; on dates the owner selects, a 22-hour stay is the only slot offered. Closed dates offer nothing.

BOOKINGS MADE (counted by the date the booking was made):
- {$now['label']}: {$now['made']} made; {$now['standing']} still standing, {$now['cancelled']} cancelled. {$this->change($now['made'], $was['made'])}.
- {$was['label']}: {$was['made']} made; {$was['standing']} still standing, {$was['cancelled']} cancelled.
- Standing bookings made {$now['label']}, by slot: {$list($now['by_slot'])}. By how they were made: {$list($now['by_source'])}.

CANCELLATIONS (of the bookings made in each period):
- {$now['label']}: {$now['cancelled']} cancelled — {$cancelledBy($now)}. {$this->change($now['cancelled'], $was['cancelled'])}.
- {$was['label']}: {$was['cancelled']} cancelled — {$cancelledBy($was)}.

SLOTS BOOKED FOR STAYS (counted by the date of the stay, for the dates in each period):
- {$now['label']}: {$now['slots_booked']} of {$now['slots_offered']} slots for sale were booked ({$pct($now['slots_pct'])}). {$this->pointChange($now['slots_pct'], $was['slots_pct'])}.
- {$was['label']}: {$was['slots_booked']} of {$was['slots_offered']} slots were booked ({$pct($was['slots_pct'])}).

MONEY RECEIVED (counted by the date it was paid; refunds subtracted):
- {$now['label']}: net {$this->peso($now['net'])} ({$this->peso($now['collected'])} collected, {$this->peso($now['refunds'])} refunded). {$this->change($now['net'], $was['net'], true)}.
- {$was['label']}: net {$this->peso($was['net'])} ({$this->peso($was['collected'])} collected, {$this->peso($was['refunds'])} refunded).

GUESTS (stays with a check-in date in each period):
- {$now['label']}: {$guestLine($now)}. New guest accounts created: {$now['new_accounts']}.
- {$was['label']}: {$guestLine($was)}. New guest accounts created: {$was['new_accounts']}.

RIGHT NOW: {$villaNow}; {$data['now']['awaiting_payment']} bookings are waiting for the guest to pay.

RULES:
1. Quote only the figures above, exactly as written. Do not calculate, round or add any number of your own: no averages, no rates, no percentages that are not written above.
2. Every comparison is already stated above ("up", "down", "the same"). Use those words; never decide a direction yourself, and never compare two figures that are not compared above.
3. Everything you say must come from the data above; it is the complete extent of what you know. Give no reasons the figures do not show — no seasons, weather, holidays, events, marketing or guest behaviour. A "returning" guest is only one the data above says had stayed before.
4. Do not predict anything and do not recommend any action. Stop at what happened.
5. The villa is one unit. Never mention rooms, properties or an occupancy rate across units.
6. These are small numbers over a few weeks. Never call a change a trend, a pattern or a sign of anything.

FORMAT: exactly 5 lines, one sentence each, in this order:
{$topics}
Start each line with one fitting emoji. No numbering, no bullets, no markdown, no heading, no text before or after the 5 lines.
PROMPT;
    }

    /**
     * "Compared with the same days last month: up by 4, from 17 (+24%)" —
     * the comparison written out, so the model never works one out. The
     * percentage is left out when the earlier figure is zero (there is none)
     * or either side is negative (it would mislead).
     */
    private function change(float $now, float $was, bool $money = false): string
    {
        $fmt = fn (float $v) => $money ? $this->peso($v) : number_format($v);
        $lead = 'Compared with the same days last month: ';

        if (round($now, 2) === round($was, 2)) {
            return $lead.'the same ('.$fmt($was).')';
        }

        $diff = abs($now - $was);
        $sentence = $lead.($now > $was ? 'up' : 'down')." by {$fmt($diff)}, from {$fmt($was)}";

        if ($was > 0 && $now >= 0) {
            $sentence .= sprintf(' (%s%d%%)', $now > $was ? '+' : '−', (int) round($diff / $was * 100));
        }

        return $sentence;
    }

    /** Booked share against the same days last month, in percentage points. */
    private function pointChange(?float $now, ?float $was): string
    {
        if ($now === null || $was === null) {
            return 'No comparison: one of the two periods had no slots for sale';
        }

        $a = (int) round($now);
        $b = (int) round($was);

        return match (true) {
            $a > $b => "That is up from {$b}% in the same days last month",
            $a < $b => "That is down from {$b}% in the same days last month",
            default => "That is the same as the same days last month ({$b}%)",
        };
    }

    private function peso(float $v): string
    {
        return Payment::formatPesos($v);
    }
}
