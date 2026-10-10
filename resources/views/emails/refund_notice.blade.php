<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $heading }} — Villa Elena Resort</title>
    {{-- Kapareho ng emails/booking_confirmed: mga kulay na nakasulat nang
         tuwiran, dahil walang stylesheet o CSS variable sa isang email
         client. Ang button ay inline ang estilo sa mismong <a> para sa mga
         client na nag-aalis ng <style>. --}}
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

        .body {
            padding: 28px 32px 32px;
        }

        .greeting {
            font-size: 15px;
            line-height: 1.6;
            margin: 0 0 16px;
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
            color: #746a59;
            text-transform: uppercase;
            letter-spacing: 1.5px;
            margin-bottom: 6px;
        }

        .ref-value {
            font-size: 24px;
            font-weight: 700;
            color: #2c2416;
        }

        .ref-sub {
            font-size: 13px;
            color: #746a59;
            margin-top: 6px;
        }

        .note-box {
            background: #fef9c3;
            border-radius: 10px;
            padding: 14px 16px;
            font-size: 14px;
            color: #a16207;
            margin-bottom: 20px;
            line-height: 1.6;
        }

        .footer {
            padding: 24px 32px;
            text-align: center;
            font-size: 12px;
            color: #746a59;
            border-top: 1px solid #e4ddd0;
        }
    </style>
</head>

<body>
    <div class="container">
        <div class="header">
            <h1>Villa Elena Resort</h1>
            <p>Refund Update</p>
        </div>

        <div class="body">
            <p class="greeting">Hi {{ $booking->user->full_name }},</p>

            <p class="greeting">{{ $body }}</p>

            <div class="ref-box">
                <div class="ref-label">Refund</div>
                <div class="ref-value">₱{{ number_format($amount, 2) }}</div>
                <div class="ref-sub">Booking {{ $booking->booking_ref }}</div>
            </div>

            @if ($actionNote)
                <div class="note-box">{{ $actionNote }}</div>
            @endif

            <p style="text-align:center;margin:0 0 24px;">
                <a href="{{ $actionUrl }}"
                    style="display:inline-block;background:#2c2416;color:#ffffff;text-decoration:none;font-size:15px;font-weight:700;padding:14px 28px;border-radius:10px;">{{ $actionLabel }}</a>
            </p>

            <p style="font-size:13px;color:#746a59;line-height:1.7;margin:0;">
                You will be asked to sign in first. If you have any questions about this refund, please contact
                the resort.
            </p>
        </div>

        <div class="footer">
            Villa Elena Resort &middot; This is an automated email about your refund.
        </div>
    </div>
</body>

</html>
