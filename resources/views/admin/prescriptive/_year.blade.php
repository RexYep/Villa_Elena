{{-- Yearly what-if: the year's twelve monthly what-ifs, each run as of its own
     start ($preview comes from WhatIfPreview::year()). View only. --}}
@php
    $months = $preview['months'];
    $chartRows = array_values(array_map(fn ($m) => [
        'label' => $m['short'],
        'enough' => $m['enough'],
        'predicted' => $m['enough'] && $m['summary'] ? round($m['summary']['predicted'], 1) : null,
        'booked' => $m['enough'] && $m['summary'] ? $m['summary']['booked'] : null,
        'offered' => $m['enough'] && $m['summary'] ? $m['summary']['offered'] : null,
        'promo' => $m['counts']['promo'],
        'peak' => $m['counts']['peak'],
        'maintenance' => $m['counts']['maintenance'],
    ], $months));
@endphp

<div class="rec-preview-banner" role="status">
    <strong>What-if for {{ $preview['label'] }}.</strong>
    Each month is run as of two days before it starts, using only the bookings made by then and the
    {{ $historyDays }} days of history before it, the way the system would have run through the year. A month that has
    not started is run as of today. Prices are today's rates. Nothing here is saved and nothing can be applied.
</div>

@if ($preview['run_months'] < 12)
    <p class="rec-status-sub">
        {{ 12 - $preview['run_months'] }} {{ 12 - $preview['run_months'] === 1 ? 'month has' : 'months have' }} no
        what-if: the booking history does not reach {{ $historyDays }} days before {{ 12 - $preview['run_months'] === 1 ? 'it' : 'them' }}.
    </p>
@endif

<section class="rec-chart-card" aria-labelledby="recYearOccTitle">
    <h3 id="recYearOccTitle" class="rec-chart-title">Predicted occupancy by month</h3>
    <p class="rec-history-note">For each month, as of its start: the larger of "booked so far" and "usual", over all its
        stretches. A missing bar is a month without enough history.</p>
    <div class="rec-chart-legend" aria-hidden="true">
        <span><i class="rec-key rec-key-line"></i>Promo below {{ $thresholds['quiet'] }}%, raise rate from
            {{ $thresholds['busy'] }}%</span>
    </div>
    <div class="rec-chart-box">
        <canvas id="recYearOcc" role="img"
            aria-label="Predicted occupancy by month for {{ $preview['label'] }}. The same numbers are in the table below."
            data-rec-chart="year-occupancy" data-chart="{{ json_encode($chartRows) }}"></canvas>
    </div>
</section>

<section class="rec-chart-card" aria-labelledby="recYearCardsTitle">
    <h3 id="recYearCardsTitle" class="rec-chart-title">Recommendations by month</h3>
    <p class="rec-history-note">How many cards each rule would have given in each month.</p>
    <div class="rec-chart-legend" aria-hidden="true">
        <span><i class="rec-key rec-key-1"></i>Promo</span>
        <span><i class="rec-key rec-key-2"></i>Raise rate</span>
        <span><i class="rec-key rec-key-3"></i>Maintenance</span>
    </div>
    <div class="rec-chart-box">
        <canvas id="recYearCards" role="img"
            aria-label="Recommendations by month for {{ $preview['label'] }}. The same numbers are in the table below."
            data-rec-chart="year-cards" data-chart="{{ json_encode($chartRows) }}"></canvas>
    </div>
</section>

<div class="table-card rec-compare">
    <div class="table-header">
        <h3>Month by month</h3>
        <span class="rec-history-note">{{ $preview['card_count'] }} {{ $preview['card_count'] === 1 ? 'card' : 'cards' }} in the year</span>
    </div>
    <table>
        <thead>
            <tr>
                <th scope="col">Month</th>
                <th scope="col">Run as of</th>
                <th scope="col">Booked so far</th>
                <th scope="col">Predicted</th>
                <th scope="col">Promo</th>
                <th scope="col">Raise rate</th>
                <th scope="col">Maintenance</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($months as $m)
                <tr>
                    <td>
                        <a href="{{ route('admin.prescriptive.index', ['view' => 'month', 'year' => $year, 'month' => $m['number']]) }}">
                            {{ $m['label'] }}
                        </a>
                    </td>
                    <td>{{ $m['as_of']->format('M j, Y') }}</td>
                    @if (!$m['enough'])
                        <td colspan="5" class="rec-history-note">Not enough history</td>
                    @elseif (!$m['summary'])
                        <td colspan="5" class="rec-history-note">No dates to judge</td>
                    @else
                        <td>{{ round($m['summary']['booked_pct']) }}% <span class="rec-history-note">({{ $m['summary']['booked'] }} of {{ $m['summary']['offered'] }})</span></td>
                        <td>{{ round($m['summary']['predicted']) }}%</td>
                        <td>{{ $m['counts']['promo'] }}</td>
                        <td>{{ $m['counts']['peak'] }}</td>
                        <td>{{ $m['counts']['maintenance'] }}</td>
                    @endif
                </tr>
            @endforeach
        </tbody>
    </table>
</div>

<h2 class="rec-section-title">The cards, month by month</h2>

@foreach ($months as $m)
    @if ($m['enough'] && count($m['cards']) > 0)
        <details class="rec-month">
            <summary>
                {{ $m['label'] }}
                <span class="rec-history-note">{{ count($m['cards']) }} {{ count($m['cards']) === 1 ? 'card' : 'cards' }}, as of {{ $m['as_of']->format('M j, Y') }}</span>
            </summary>
            @foreach ($m['cards'] as $rec)
                @include('admin.prescriptive._preview_card', ['rec' => $rec])
            @endforeach
        </details>
    @endif
@endforeach
