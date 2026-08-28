<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Booking Confirmed — Villa Elena Resort</title>
    <style>
        body {
            font-family: 'Helvetica Neue', Arial, sans-serif;
            background: #f5f0e8;
            margin: 0;
            padding: 0;
            color: #2c2416;
        }

        .container {
            max-width: 520px;
            margin: 0 auto;
            background: #ffffff;
        }

        .header {
            background: #2c2416;
            padding: 32px;
            text-align: center;
        }

        .header h1 {
            color: #d4aa5a;
            font-size: 22px;
            margin: 0;
            font-weight: 700;
            letter-spacing: .3px;
        }

        .header p {
            color: rgba(255, 255, 255, .5);
            font-size: 12px;
            margin-top: 6px;
            text-transform: uppercase;
            letter-spacing: 1.5px;
        }

        .badge {
            display: inline-block;
            background: #dcfce7;
            color: #15803d;
            font-size: 12px;
            font-weight: 700;
            padding: 6px 16px;
            border-radius: 20px;
            margin: 24px auto 0;
        }

        .body {
            padding: 8px 32px 32px;
        }

        .greeting {
            font-size: 15px;
            margin-bottom: 16px;
        }

        .ref-box {
            background: #f5f0e8;
            border-radius: 12px;
            padding: 20px;
            text-align: center;
            margin-bottom: 24px;
        }

        .ref-label {
            font-size: 11px;
            color: #8a7f6e;
            text-transform: uppercase;
            letter-spacing: 1.5px;
            margin-bottom: 6px;
        }

        .ref-value {
            font-size: 24px;
            font-weight: 700;
            color: #2c2416;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            font-size: 14px;
            margin-bottom: 20px;
        }

        td {
            padding: 9px 0;
            border-bottom: 1px solid #e4ddd0;
        }

        td.label {
            color: #8a7f6e;
        }

        td.value {
            text-align: right;
            font-weight: 500;
            color: #2c2416;
        }

        .total-row td {
            font-weight: 700;
            font-size: 16px;
            border-top: 2px solid #e4ddd0;
            border-bottom: none;
            padding-top: 14px;
        }

        .balance-row td {
            color: #c4673a;
            font-weight: 700;
        }

        .note-box {
            background: #fef9c3;
            border-radius: 10px;
            padding: 14px 16px;
            font-size: 13px;
            color: #a16207;
            margin-bottom: 20px;
            line-height: 1.6;
        }

        .footer {
            padding: 24px 32px;
            text-align: center;
            font-size: 12px;
            color: #8a7f6e;
            border-top: 1px solid #e4ddd0;
        }
    </style>
</head>

<body>
    <div class="container">
        <div class="header">
            <h1>Villa Elena Resort</h1>
            <p>Booking Confirmation</p>
        </div>

        <div style="text-align:center;">
            <span class="badge">✓ Booking Confirmed</span>
        </div>

        <div class="body">
            <p class="greeting">Hi {{ $booking->user->full_name }},</p>
            <p class="greeting">Great news — your payment has been received and your booking is now
                <strong>confirmed</strong>. We're excited to host you!</p>

            <div class="ref-box">
                <div class="ref-label">Booking Reference</div>
                <div class="ref-value">{{ $booking->booking_ref }}</div>
            </div>

            <table>
                <tr>
                    <td class="label">Property</td>
                    <td class="value">{{ $booking->property->property_name }}</td>
                </tr>
                <tr>
                    <td class="label">Check-in</td>
                    <td class="value">{{ $booking->check_in_date->format('M d, Y') }} —
                        {{ \Carbon\Carbon::parse($booking->check_in_time)->format('g:i A') }}</td>
                </tr>
                <tr>
                    <td class="label">Check-out</td>
                    <td class="value">{{ $booking->check_out_date->format('M d, Y') }} —
                        {{ \Carbon\Carbon::parse($booking->check_out_time)->format('g:i A') }}</td>
                </tr>
                <tr>
                    <td class="label">Guests</td>
                    <td class="value">{{ $booking->num_guests }} guest{{ $booking->num_guests != 1 ? 's' : '' }}</td>
                </tr>
                <tr>
                    <td class="label">Total Amount</td>
                    <td class="value">₱{{ number_format($booking->total_amount, 2) }}</td>
                </tr>
                <tr>
                    <td class="label">Amount Paid</td>
                    <td class="value">₱{{ number_format($booking->amount_paid, 2) }}</td>
                </tr>
                @if ($booking->balance_due > 0)
                    <tr class="total-row balance-row">
                        <td>Balance Due (to be paid at check-in)</td>
                        <td class="value">₱{{ number_format($booking->balance_due, 2) }}</td>
                    </tr>
                @else
                    <tr class="total-row">
                        <td>Status</td>
                        <td class="value" style="color:#15803d;">Fully Paid</td>
                    </tr>
                @endif
            </table>

            @if ($booking->balance_due > 0)
                <div class="note-box">
                    <strong>Reminder:</strong> There is a remaining balance of
                    ₱{{ number_format($booking->balance_due, 2) }} to be paid before or on the day of check-in.
                </div>
            @endif

            <p style="font-size:13px;color:#8a7f6e;line-height:1.7;">
                If you have any questions or need help regarding your booking, don't hesitate to contact us.
            </p>
        </div>

        <div class="footer">
            Villa Elena Resort &middot; This is an automated confirmation email.
        </div>
    </div>
</body>

</html>
