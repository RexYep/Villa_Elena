{{-- View-only history filter. Nothing on this page is saved, so no card has
     an Apply or Dismiss button: those need a saved card, and the nightly
     rules (Default) stay the one thing that acts. --}}
@php
    $pct = fn (array $row) => $row['offered'] > 0 ? (int) round($row['booked'] / $row['offered'] * 100) : 0;
    $cardCount = count($preview['cards']);
@endphp

<div class="rec-preview-banner" role="status">
    <strong>Preview.</strong> "Usual" is measured over the {{ strtolower($preview['label']) }} instead of the last
    {{ $defaultHistoryDays }} days. Nothing here is saved and nothing can be applied. To act on a card,
    <a href="{{ route('admin.prescriptive.index') }}">switch to Default</a>.
</div>

@if (!empty($preview['failed']))
    <div class="alert alert-danger">
        Some rules could not run for this preview, so their cards are missing. The error is in the log.
    </div>
@endif

<div class="table-card rec-compare">
    <div class="table-header">
        <h3>Usual by weekday</h3>
        <span class="rec-history-note">Day and Night slots booked ÷ slots offered</span>
    </div>
    <table>
        <thead>
            <tr>
                <th scope="col">Weekday</th>
                <th scope="col">Default ({{ $defaultHistoryDays }} days)</th>
                <th scope="col">{{ $preview['label'] }}</th>
                <th scope="col">Change</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($preview['weekdays'] as $row)
                @php
                    $was = $pct($row['default']);
                    $now = $pct($row['chosen']);
                    $diff = $now - $was;
                @endphp
                <tr>
                    <td>{{ $row['name'] }}</td>
                    <td>
                        {{ $was }}%
                        <div class="rec-history-note">{{ $row['default']['booked'] }} of {{ $row['default']['offered'] }} slots</div>
                    </td>
                    <td>
                        {{ $now }}%
                        <div class="rec-history-note">{{ $row['chosen']['booked'] }} of {{ $row['chosen']['offered'] }} slots</div>
                    </td>
                    <td>
                        @if ($diff > 0)
                            <span class="rec-up">+{{ $diff }} pts</span>
                        @elseif ($diff < 0)
                            <span class="rec-down">−{{ abs($diff) }} pts</span>
                        @else
                            Same
                        @endif
                    </td>
                </tr>
            @endforeach
        </tbody>
    </table>
</div>

<h2 class="rec-section-title">
    {{ $cardCount }} {{ $cardCount === 1 ? 'card' : 'cards' }} with the {{ strtolower($preview['label']) }}
</h2>

@forelse ($preview['cards'] as $item)
    @php $rec = $item['rec']; @endphp
    <article class="rec-card">
        <div class="rec-kind-icon {{ $rec->action_tag_class }}" aria-hidden="true">
            <i class="bi bi-{{ $rec->action_icon }}"></i>
        </div>
        <div>
            <div class="rec-kind">
                {{ $rec->action_label }}
                @if ($item['same'])
                    <span class="badge tag-neutral">Same as Default</span>
                @else
                    <span class="badge tag-amber">Only in this view</span>
                @endif
            </div>
            <h3 class="rec-title">{{ $rec->title }}</h3>
            <p class="rec-summary">{{ $rec->summary }}</p>

            @if (!empty($rec->evidence))
                <ul class="rec-facts">
                    @foreach ($rec->evidence as $fact)
                        <li>{{ $fact }}</li>
                    @endforeach
                </ul>
            @endif
        </div>
    </article>
@empty
    <div class="rec-empty">
        With this much history, no rule suggests a promo, a higher rate or a maintenance date.
    </div>
@endforelse

@if (!empty($preview['gone']))
    <h2 class="rec-section-title">Default cards that disappear here</h2>
    <ul class="rec-gone">
        @foreach ($preview['gone'] as $rec)
            <li>{{ $rec->title }}</li>
        @endforeach
    </ul>
@endif

<p class="rec-status-sub">
    Only the promo, peak rate and maintenance rules use history, so only they are shown. Turnover cleans and balance
    reminders are the same in every view; see them under Default.
</p>
