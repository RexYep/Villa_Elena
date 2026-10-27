{{-- Booked so far and usual per stretch, with the quiet and busy marks.
     Needs $chartId, $chartTitle, $chartNote, $stretches (WhatIfPreview rows)
     and $thresholds. The script in index.blade.php draws every
     [data-rec-chart]; the table below is the same numbers for anyone who
     cannot use the chart. --}}
<section class="rec-chart-card" aria-labelledby="{{ $chartId }}Title">
    <h3 id="{{ $chartId }}Title" class="rec-chart-title">{{ $chartTitle }}</h3>
    <p class="rec-history-note">{{ $chartNote }}</p>

    @if (empty($stretches))
        <p class="rec-history-note">There are no dates to show.</p>
    @else
        <div class="rec-chart-legend" aria-hidden="true">
            <span><i class="rec-key rec-key-1"></i>Booked so far</span>
            <span><i class="rec-key rec-key-2"></i>Usual</span>
            <span><i class="rec-key rec-key-line"></i>Promo below {{ $thresholds['quiet'] }}%, raise rate from
                {{ $thresholds['busy'] }}%</span>
        </div>
        <div class="rec-chart-box">
            <canvas id="{{ $chartId }}" role="img"
                aria-label="{{ $chartTitle }}. The same numbers are in the table below."
                data-rec-chart="stretch"
                data-chart="{{ json_encode(array_map(fn ($s) => [
                    'label' => [$s['label'], $s['kind']],
                    'booked_pct' => $s['booked_pct'],
                    'usual_pct' => $s['usual_pct'],
                    'predicted' => $s['predicted'],
                    'booked' => $s['booked'],
                    'offered' => $s['offered'],
                ], $stretches)) }}"></canvas>
        </div>

        <details class="rec-chart-table">
            <summary>Show as a table</summary>
            <div class="table-card">
                <table>
                    <thead>
                        <tr>
                            <th scope="col">Dates</th>
                            <th scope="col">Booked so far</th>
                            <th scope="col">Usual</th>
                            <th scope="col">Predicted</th>
                            <th scope="col">Card</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($stretches as $s)
                            <tr>
                                <td>{{ $s['label'] }} <span class="rec-history-note">({{ $s['kind'] }})</span></td>
                                <td>{{ round($s['booked_pct']) }}% <span class="rec-history-note">({{ $s['booked'] }} of {{ $s['offered'] }})</span></td>
                                <td>{{ round($s['usual_pct']) }}%</td>
                                <td>{{ round($s['predicted']) }}%</td>
                                <td>{{ empty($s['cards']) ? '—' : implode('; ', $s['cards']) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </details>
    @endif
</section>
