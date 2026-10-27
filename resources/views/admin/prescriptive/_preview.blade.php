{{-- Monthly / Quarterly what-if: "What would the system have recommended back
     then?" ($preview comes from WhatIfPreview::month() / quarter()). Nothing
     here is saved, so no card has an Apply or Dismiss button. --}}
@php
    $pct = fn (array $row) => $row['offered'] > 0 ? (int) round($row['booked'] / $row['offered'] * 100) : 0;
    $asOf = $preview['as_of']->format('M j, Y');
@endphp

<div class="rec-preview-banner" role="status">
    <strong>What-if for {{ $preview['label'] }} ({{ $preview['dates'] }}).</strong>
    @if ($preview['is_past'])
        These are the cards the system would have shown on {{ $asOf }}, using only the bookings made by that day.
    @else
        This period has not started, so these are the cards the system shows today for its dates.
    @endif
    "Usual" is from the {{ $preview['history_days'] }} days before {{ $asOf }} ({{ $preview['history_dates'] }}).
    Prices on the cards are today's rates. Nothing here is saved and nothing can be applied.
</div>

@if (!$preview['enough'])
    <div class="rec-empty">
        Not enough booking history for {{ $preview['label'] }}. "Usual" needs bookings from
        {{ $preview['needs_from']->format('M j, Y') }}, but the booking history starts
        {{ $preview['data_start'] ? $preview['data_start']->format('M j, Y') : 'later' }}. Without it, the empty days would
        count as "nobody booked" and every week would get a promo card.
    </div>
@else
    @if (!empty($preview['failed']))
        <div class="alert alert-danger">
            Some rules could not run for this what-if, so their cards are missing. The error is in the log.
        </div>
    @endif

    <div class="table-card rec-compare">
        <div class="table-header">
            <h3>Usual by weekday on {{ $asOf }}</h3>
            <span class="rec-history-note">Day and Night slots booked ÷ slots offered, {{ $preview['history_dates'] }}</span>
        </div>
        <table>
            <thead>
                <tr>
                    <th scope="col">Weekday</th>
                    <th scope="col">Usual</th>
                    <th scope="col">Slots</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($preview['weekdays'] as $row)
                    <tr>
                        <td>{{ $row['name'] }}</td>
                        <td>{{ $pct($row) }}%</td>
                        <td>{{ $row['booked'] }} of {{ $row['offered'] }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    @include('admin.prescriptive._stretch_chart', [
        'chartId' => 'recPeriodChart',
        'chartTitle' => 'Booked so far and usual, week by week',
        'chartNote' => 'As of '.$asOf.'. Each pair of bars is one stretch: the weekdays (Mon–Thu) or the weekend (Fri–Sun) of a week. Predicted occupancy is the taller of the two bars.',
        'stretches' => $preview['stretches'],
    ])

    @php $cardCount = count($preview['cards']); @endphp
    <h2 class="rec-section-title">
        {{ $cardCount }} {{ $cardCount === 1 ? 'card' : 'cards' }} for {{ $preview['label'] }}
        @if ($preview['is_past'])
            <span class="rec-history-note">as of {{ $asOf }}</span>
        @endif
    </h2>

    @forelse ($preview['cards'] as $rec)
        @include('admin.prescriptive._preview_card', ['rec' => $rec])
    @empty
        <div class="rec-empty">
            No rule suggests a promo, a higher rate or a maintenance date for {{ $preview['label'] }}.
        </div>
    @endforelse

    <p class="rec-status-sub">
        Only the promo, peak rate and maintenance rules use history, so only they are shown. Turnover cleans and balance
        reminders are about the coming days; see them under Default.
    </p>
@endif
