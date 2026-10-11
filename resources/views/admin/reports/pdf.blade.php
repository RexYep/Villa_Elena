<!DOCTYPE html>
<html>

<head>
    <meta charset="utf-8">
    <title>Villa Elena — Report</title>
    <style>
        /* DejaVu Sans, not the default: dompdf's built-in fonts have no peso
           sign, so every amount printed as "?49,006.00". DejaVu ships with
           dompdf, so there is nothing to install. */
        body {
            font-family: "DejaVu Sans", sans-serif;
            font-size: 12px;
            color: #1a1a1a;
        }

        h1 {
            font-size: 19px;
            margin-bottom: 2px;
        }

        .subtitle {
            color: #555;
            margin-bottom: 4px;
        }

        .basis {
            color: #555;
            font-size: 10px;
            margin-bottom: 16px;
        }

        h2 {
            font-size: 13px;
            margin-top: 20px;
            margin-bottom: 6px;
            border-bottom: 1px solid #ccc;
            padding-bottom: 4px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 8px;
        }

        th,
        td {
            border: 1px solid #ddd;
            padding: 5px 8px;
            text-align: left;
            font-size: 11px;
        }

        th {
            background: #f2ece3;
        }

        .kpi-table td:first-child {
            font-weight: bold;
            width: 55%;
        }

        .text-right {
            text-align: right;
        }

        .note {
            color: #555;
            font-size: 10px;
        }
    </style>
</head>

<body>
    <h1>Villa Elena Resort — Report</h1>
    <div class="subtitle">Period: {{ $from->format('M d, Y') }} — {{ $to->format('M d, Y') }}</div>
    <div class="basis">
        Bookings are counted by the date they were made, payments by the date they were paid,
        and occupancy by the date of the stay.
        @if ($runsPastToday)
            This period runs past today, so occupancy includes dates still to come.
        @endif
    </div>

    <h2>Summary</h2>
    <table class="kpi-table">
        <tr>
            <td>Collected</td>
            <td>₱{{ number_format($totalRevenue, 2) }}</td>
        </tr>
        <tr>
            <td>Refunds</td>
            <td>₱{{ number_format($totalRefunds, 2) }}</td>
        </tr>
        <tr>
            <td>Net Revenue</td>
            <td>{{ \App\Models\Payment::formatPesos($netRevenue, 2) }}</td>
        </tr>
        <tr>
            <td>Bookings Made</td>
            <td>{{ $totalBookings }}</td>
        </tr>
        <tr>
            <td>Of those, cancelled</td>
            <td>{{ $cancelledBookings }}</td>
        </tr>
        <tr>
            <td>Of those, no show</td>
            <td>{{ $noShowBookings }}</td>
        </tr>
        <tr>
            <td>Of those, still standing</td>
            <td>{{ $activeBookings }}</td>
        </tr>
        <tr>
            <td>Average Booking Value</td>
            <td>₱{{ number_format($avgBookingValue, 2) }}</td>
        </tr>
        <tr>
            <td>Occupancy Rate</td>
            <td>
                @if ($occupancy['offered'] > 0)
                    {{ $occupancyRate }}% ({{ $occupancy['booked'] }} of {{ $occupancy['offered'] }} slots booked)
                @else
                    No slots were open for booking
                @endif
            </td>
        </tr>
        <tr>
            <td>New Guests</td>
            <td>{{ $newGuests }}</td>
        </tr>
    </table>

    <h2>Bookings by Slot</h2>
    @if (empty($bySlot))
        <p>No bookings were made in this period.</p>
    @else
        <table>
            <thead>
                <tr>
                    <th>Slot</th>
                    <th class="text-right">Bookings</th>
                    <th class="text-right">Booked Value</th>
                    <th class="text-right">Avg / Booking</th>
                    <th class="text-right">Share</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($bySlot as $row)
                    <tr>
                        <td>{{ $row['label'] }} — {{ $row['times'] }}</td>
                        <td class="text-right">{{ $row['bookings'] }}</td>
                        <td class="text-right">₱{{ number_format($row['value'], 2) }}</td>
                        <td class="text-right">₱{{ number_format($row['average'], 2) }}</td>
                        <td class="text-right">{{ $row['share'] }}%</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
        <p class="note">
            Bookings made in the period, cancelled and no-show left out. Booked value is the price of
            these bookings, not the money collected.
        </p>
    @endif

    @if (! empty($bookingsBySource))
        <h2>Bookings by Source and Status</h2>
        <table>
            <thead>
                <tr>
                    <th>Source</th>
                    <th class="text-right">Bookings</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($bookingsBySource as $row)
                    <tr>
                        <td>{{ $row['label'] }}</td>
                        <td class="text-right">{{ $row['count'] }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
        <table>
            <thead>
                <tr>
                    <th>Status now</th>
                    <th class="text-right">Bookings</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($bookingsByStatus as $row)
                    <tr>
                        <td>{{ $row['label'] }}</td>
                        <td class="text-right">{{ $row['count'] }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif

    {{-- Kept together: split across two pages, the last eight months sat
         alone under a repeated heading. --}}
    <div style="page-break-inside: avoid;">
    <h2>Net Revenue, Last 12 Months</h2>
    <table>
        <thead>
            <tr>
                <th>Month</th>
                <th class="text-right">Net revenue (after refunds)</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($revenueByMonth as $row)
                <tr>
                    <td>{{ $row['label'] }}</td>
                    <td class="text-right">{{ \App\Models\Payment::formatPesos($row['amount'], 2) }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
    </div>

    <p style="margin-top:18px;color:#888;font-size:10px;">Generated {{ now()->format('M d, Y g:i A') }}</p>
</body>

</html>
