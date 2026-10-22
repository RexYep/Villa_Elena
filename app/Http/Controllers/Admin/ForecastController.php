<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Concerns\ThrottlesAiRefresh;
use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\Payment;
use App\Models\Setting;
use App\Services\AiReportStore;
use App\Services\Forecast\MonthlyOutlook;
use App\Services\GeminiService;
use League\CommonMark\CommonMarkConverter;

/**
 * /admin/forecast — what the next three months are likely to bring (v7.82).
 *
 * The NUMBERS are worked out by `MonthlyOutlook`, by rule, on every visit.
 * The AI only writes the explanation around them, and is told not to add a
 * number of its own. Until v7.82 the AI produced the numbers too, and they
 * could not be traced to any method (see MonthlyOutlook's docblock).
 *
 * Still an outlook only: what to DO about it is /admin/prescriptive. If this
 * prompt ever asks for advice again, the two pages will contradict each other.
 */
class ForecastController extends Controller
{
    use ThrottlesAiRefresh;

    /**
     * Bumped when the prompt's inputs change shape. A stored report from an
     * older version was written from figures this page no longer shows (the
     * v7.81 one invented its own), so it is treated as no report at all.
     */
    private const REPORT_VERSION = 2;

    /**
     * Reads the stored explanation; calls the AI only when there is none yet
     * (or only one from an older version). The figures are live either way.
     */
    public function index(AiReportStore $store, GeminiService $ai, MonthlyOutlook $outlook)
    {
        $data = $outlook->build();
        $report = $store->read(AiReportStore::FORECAST);

        if (! $this->isCurrentVersion($report)) {
            $report = $store->refresh(
                AiReportStore::FORECAST,
                fn () => $this->ask($ai, $data),
                $this->reportMeta($data)
            );
        }

        return $this->render($data, $report);
    }

    /**
     * The Refresh button. The ONLY path that spends an AI request on purpose.
     */
    public function refresh(AiReportStore $store, GeminiService $ai, MonthlyOutlook $outlook)
    {
        if ($wait = $this->aiRefreshCooldown(AiReportStore::FORECAST)) {
            return back()->with('error', $this->aiRefreshCooldownMessage('forecast', $wait));
        }

        $data = $outlook->build();

        // NULL means "not regenerated" — `refresh()` leaves the previous
        // explanation in place.
        $refreshed = $store->refresh(
            AiReportStore::FORECAST,
            fn () => $this->ask($ai, $data),
            $this->reportMeta($data)
        );

        return $refreshed === null
            ? back()->with('error', $this->aiFailureMessage(
                $ai,
                'The explanation could not be rewritten',
                'The forecast figures above are worked out without the AI and are unaffected.'
            ))
            : back()->with('success', 'Forecast explanation rewritten from the current figures.');
    }

    private function ask(GeminiService $ai, array $data): ?string
    {
        // 2048: the explanation is several sections of Markdown, and hidden
        // reasoning is billed against the same cap (at 1024 it was cut off).
        // 0.3: it restates given figures; warmth only adds invented ones.
        return $ai->ask($this->prompt($data), 2048, 0.3);
    }

    /**
     * @param  array{text: string, generated_at: \Carbon\Carbon, meta: ?array}|null  $report
     */
    private function isCurrentVersion(?array $report): bool
    {
        return $report !== null && ($report['meta']['version'] ?? null) === self::REPORT_VERSION;
    }

    /**
     * What the explanation was written from, kept with it so the page can
     * say when it no longer matches the live figures.
     *
     * @return array<string, mixed>
     */
    private function reportMeta(array $data): array
    {
        return [
            'version' => self::REPORT_VERSION,
            'first_month' => $this->firstForecastMonth($data)['key'] ?? null,
            'figures' => $this->figures($data),
        ];
    }

    /**
     * The figures the explanation quotes, for the months it covers.
     *
     * @return array<string, array{0: int, 1: int, 2: int}>
     */
    private function figures(array $data): array
    {
        $figures = [];

        foreach ($data['months'] as $m) {
            if ($m['state'] !== 'past') {
                $figures[$m['key']] = [$m['booked'], $m['expected'], (int) round($m['value_expected'])];
            }
        }

        return $figures;
    }

    private function firstForecastMonth(array $data): ?array
    {
        foreach ($data['months'] as $m) {
            if ($m['state'] !== 'past') {
                return $m;
            }
        }

        return null;
    }

    /**
     * @param  array{text: string, generated_at: \Carbon\Carbon, meta: ?array}|null  $report
     */
    private function render(array $data, ?array $report)
    {
        // An explanation from before v7.82 quotes numbers the AI made up;
        // showing it under the calculated table would put two forecasts on
        // one page.
        if (! $this->isCurrentVersion($report)) {
            $report = null;
        }

        // The AI writes Markdown. `html_input => 'escape'` and
        // `allow_unsafe_links => false` because the content is model output.
        $forecastHtml = null;
        $reportState = null;

        if ($report !== null) {
            $converter = new CommonMarkConverter([
                'html_input' => 'escape',
                'allow_unsafe_links' => false,
            ]);
            $forecastHtml = (string) $converter->convert($report['text']);

            // Which months it covers is decided by the month it was written
            // in; the figures move whenever a booking does.
            $reportState = match (true) {
                ($report['meta']['first_month'] ?? null) !== ($this->firstForecastMonth($data)['key'] ?? null) => 'months_moved',
                ($report['meta']['figures'] ?? null) !== $this->figures($data) => 'figures_changed',
                default => 'current',
            };
        }

        return view('admin.forecast.index', [
            'outlook' => $data,
            'forecastHtml' => $forecastHtml,
            'reportState' => $reportState,
            'generatedAt' => $report['generated_at'] ?? null,
        ]);
    }

    /**
     * The facts the AI may use — and nothing else (the v7.20 grounding rule:
     * the prompt is the complete extent of what it knows, and a denial is a
     * claim too). Every figure is printed here already formatted, so the
     * model copies numbers instead of working them out.
     */
    private function prompt(array $data): string
    {
        $peso = fn (float $v) => Payment::formatPesos($v);
        $pct = fn (?float $v) => $v === null ? '—' : number_format($v, 0).'%';

        // Comparisons are stated, not left to the model: asked to compare the
        // percentages itself, it called July's 6% "above" a 12% usual fill.
        $usualRounded = (int) round($data['usual_pct']);
        $versusUsual = fn (float $v) => match (true) {
            (int) round($v) > $usualRounded => 'above the usual fill',
            (int) round($v) < $usualRounded => 'below the usual fill',
            default => 'the same as the usual fill',
        };

        $history = '';
        $ahead = '';
        $groups = [];
        $latest = null;

        foreach ($data['months'] as $m) {
            if ($m['state'] === 'past') {
                $history .= "- {$m['name']}: {$m['booked']} of {$m['offered']} slots booked ({$pct($m['booked_pct'])}, {$versusUsual($m['booked_pct'])}); stays worth {$peso($m['value_booked'])}.\n";
                $groups[$versusUsual($m['booked_pct'])][] = $m['name'];
                $latest = $m;

                continue;
            }

            $line = $m['state'] === 'current'
                ? "- {$m['name']} (this month, {$m['days_left']} days left including today): {$m['booked']} of {$m['offered']} slots booked so far; {$m['ahead_booked']} of the {$m['ahead_offered']} slots still ahead are already booked."
                : "- {$m['name']}: {$m['booked']} of {$m['offered']} slots booked so far ({$pct($m['booked_pct'])}).";

            $line .= " Usual fill for these weekdays: {$pct($m['usual_pct'])}."
                ." Expected: {$m['expected']} slots"
                .($m['extra'] > 0 ? " ({$m['booked']} already booked + {$m['extra']} more expected)" : ' (all of them already booked; none more expected)')
                ."; stays worth about {$peso($m['value_expected'])}, of which {$peso($m['value_booked'])} is already booked."
                .match (true) {
                    $m['extra'] === 0 => ' This figure is fully secured by bookings already made.',
                    $m['booked'] >= $m['extra'] => ' This figure rests mostly on bookings already made.',
                    default => ' This figure rests mostly on the usual fill, not on bookings already made.',
                };

            if ($m['open_price'] !== null) {
                $line .= " Open slots are listed at {$peso($m['open_price'])} on average.";
            }

            if ($m['holidays'] !== []) {
                $line .= ' Public holidays: '.collect($m['holidays'])
                    ->map(fn ($h) => $h['date']->format('D, M j').' ('.$h['name'].')')
                    ->implode('; ').'.';
            }

            $ahead .= $line."\n";
        }

        // Said in one sentence as well, because a per-line label was not
        // enough: given the five lines above, the model still summed them up
        // as "recent months were below the usual fill" with September at 30%.
        $trend = collect($groups)
            ->map(fn ($names, $label) => ucfirst($label).': '.implode(', ', $names).'.')
            ->implode(' ');

        if ($latest !== null) {
            $trend .= " The most recent finished month, {$latest['name']}, was {$versusUsual($latest['booked_pct'])}.";
        }

        $slots = collect(Booking::SLOTS)
            ->map(fn ($s) => $s['name'].' ('.$s['times'].')')
            ->implode(', ');

        $resort = Setting::resortName();
        $usual = $pct($data['usual_pct']);
        $days = $data['history_days'];

        return <<<PROMPT
You are writing the explanation for a booking forecast of {$resort}, a single private villa in the Philippines that is rented whole to one guest group at a time.

The forecast has ALREADY BEEN CALCULATED by the system. Your only job is to explain it to the owner in plain, formal words.

WHAT A SLOT IS: one stay the villa can sell. The slots are: {$slots}. An ordinary date offers Day and Night; on dates the owner selects, a 22-hour stay is the only slot offered. Closed dates offer nothing.

HOW THE EXPECTED FIGURES WERE CALCULATED:
- For dates still ahead: the larger of (a) the share of slots already booked and (b) the usual share — how full the same weekdays were over the last {$days} days ({$usual} across all weekdays).
- Dates already past keep what they actually got.
- Expected value = value already booked + the extra expected slots × the average price the open slots are listed at today.
- "Value" is the price of the stays, by the month of the stay. It is not money received.

FINISHED MONTHS:
{$history}
FINISHED MONTHS AGAINST THE USUAL FILL: {$trend}

THIS MONTH AND THE NEXT THREE:
{$ahead}
RULES:
1. Quote only the figures above, exactly as written. Do not calculate, adjust or add any number of your own: no growth rates, no averages, no ranges, no new estimates.
2. Everything you say must come from the data above; it is the complete extent of what you know. Do not claim tourism seasons, weather, events, competitors or demand patterns that the figures do not show. You may say that a listed public holiday falls in a month, but not how much it will change bookings. If the history is too short to show a pattern, say so.
3. Do not recommend any action, discount, price change or promotion. Stop at the outlook.
4. The villa is one unit. Never mention rooms, properties or an occupancy rate across units.
5. "Secured" is not "full". Never call a month fully booked unless its booked slots equal its slots for sale.

WHAT TO EXPLAIN: the owner sees every figure above in a table beside your text, so do not restate them line by line. Explain what they mean: what each expected figure rests on (each month says so above), and which finished months ran above or below the usual fill (each says so above — never decide that yourself), because that is the main reason an expected figure could turn out too low or too high.

FORMAT (Markdown, under 300 words):
- Start with a two- or three-sentence overview of the whole period.
- Then one "###" heading per month in "THIS MONTH AND THE NEXT THREE", each with 2 bullets: what its expected figure rests on, and any public holiday listed for it (leave the holiday bullet out if none is listed).
- End with "### What could change these numbers": 3 short bullets, each tied to a figure above.
PROMPT;
    }
}
