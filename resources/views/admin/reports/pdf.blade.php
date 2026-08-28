<!DOCTYPE html>
<html>

<head>
    <meta charset="utf-8">
    <title>Villa Elena — Report</title>
    <style>
        body {
            font-family: sans-serif;
            font-size: 12px;
            color: #1a1a1a;
        }

        h1 {
            font-size: 20px;
            margin-bottom: 2px;
        }

        .subtitle {
            color: #555;
            margin-bottom: 18px;
        }

        h2 {
            font-size: 14px;
            margin-top: 22px;
            margin-bottom: 6px;
            border-bottom: 1px solid #ccc;
            padding-bottom: 4px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 10px;
        }

        th,
        td {
            border: 1px solid #ddd;
            padding: 6px 8px;
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
    </style>
</head>

<body>
    <h1>Villa Elena Resort — Report</h1>
    <div class="subtitle">Period: {{ $from->format('M d, Y') }} — {{ $to->format('M d, Y') }}</div>

    <h2>Summary</h2>
    <table class="kpi-table">
        <tr>
            <td>Net Revenue</td>
            <td>&#8369;{{ number_format($netRevenue, 2) }}</td>
        </tr>
        <tr>
            <td>Total Refunds</td>
            <td>&#8369;{{ number_format($totalRefunds, 2) }}</td>
        </tr>
        <tr>
            <td>Total Bookings</td>
            <td>{{ $totalBookings }}</td>
        </tr>
        <tr>
            <td>Confirmed Bookings</td>
            <td>{{ $confirmedBookings }}</td>
        </tr>
        <tr>
            <td>Cancelled Bookings</td>
            <td>{{ $cancelledBookings }}</td>
        </tr>
        <tr>
            <td>New Guests</td>
            <td>{{ $newGuests }}</td>
        </tr>
        <tr>
            <td>Occupancy Rate</td>
            <td>{{ $occupancyRate }}%</td>
        </tr>
        <tr>
            <td>Average Booking Value</td>
            <td>&#8369;{{ number_format($avgBookingValue, 2) }}</td>
        </tr>
    </table>

    <h2>Revenue — Last 12 Months</h2>
    <table>
        <thead>
            <tr>
                <th>Month</th>
                <th class="text-right">Revenue</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($revenueByMonth as $row)
                <tr>
                    <td>{{ $row['label'] }}</td>
                    <td class="text-right">&#8369;{{ number_format($row['amount'], 2) }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <h2>Top Properties by Revenue</h2>
    @if ($topProperties->isEmpty())
        <p>No booking data for this period.</p>
    @else
        <table>
            <thead>
                <tr>
                    <th>Property</th>
                    <th class="text-right">Bookings</th>
                    <th class="text-right">Revenue</th>
                    <th class="text-right">Avg / Booking</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($topProperties as $row)
                    <tr>
                        <td>{{ $row->property->property_name ?? 'N/A' }}</td>
                        <td class="text-right">{{ $row->bookings }}</td>
                        <td class="text-right">&#8369;{{ number_format($row->revenue, 2) }}</td>
                        <td class="text-right">
                            &#8369;{{ number_format($row->bookings > 0 ? $row->revenue / $row->bookings : 0, 2) }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif

    <p style="margin-top:20px;color:#888;font-size:10px;">Generated {{ now()->format('M d, Y g:i A') }}</p>
</body>

</html>

