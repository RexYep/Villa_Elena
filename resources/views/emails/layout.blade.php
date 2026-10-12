{{-- The frame every email to a guest is printed in: the resort's name, one
     headline saying what happened, the message, and how to reach the resort.

     Colours are written out because an email client has no stylesheet and no
     CSS variable. Anything that must survive a client that strips the style
     block (the button, the hidden preview line) is styled on the element
     itself. A view passes `heading` and `preheader`, and may fill the
     `footer_note` section to say why the guest received it. --}}
@php $resort = \App\Support\GuestEmail::contact(); @endphp
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $heading }} — {{ $resort['name'] }}</title>
    <style>
        body {
            margin: 0;
            padding: 0;
            background: #f5f0e8;
            color: #2c2416;
            font-family: -apple-system, 'Segoe UI', 'Helvetica Neue', Arial, sans-serif;
            font-size: 15px;
            line-height: 1.6;
        }

        .page {
            width: 100%;
            background: #f5f0e8;
        }

        .sheet {
            width: 100%;
            max-width: 560px;
            background: #ffffff;
        }

        .brand {
            background: #2c2416;
            color: #d4aa5a;
            padding: 22px 32px;
            font-family: Georgia, 'Times New Roman', serif;
            font-size: 20px;
            font-weight: 700;
            letter-spacing: .2px;
        }

        .content {
            padding: 30px 32px 28px;
        }

        .content h1 {
            margin: 0 0 18px;
            font-family: Georgia, 'Times New Roman', serif;
            font-size: 25px;
            line-height: 1.25;
            font-weight: 700;
            color: #2c2416;
        }

        .content p {
            margin: 0 0 16px;
        }

        .panel {
            background: #f5f0e8;
            border-radius: 10px;
            padding: 18px 20px;
            margin: 0 0 22px;
        }

        .panel-label {
            font-size: 13px;
            color: #746a59;
        }

        .panel-value {
            font-size: 26px;
            line-height: 1.3;
            font-weight: 700;
            color: #2c2416;
        }

        .panel-code {
            font-family: 'Courier New', Courier, monospace;
            font-size: 34px;
            line-height: 1.3;
            font-weight: 700;
            letter-spacing: 6px;
            color: #2c2416;
        }

        .panel-sub {
            font-size: 13px;
            color: #746a59;
            margin-top: 2px;
        }

        .rows {
            width: 100%;
            border-collapse: collapse;
            margin: 0 0 22px;
            font-size: 14px;
        }

        .rows td {
            padding: 9px 0;
            border-bottom: 1px solid #e4ddd0;
            vertical-align: top;
        }

        .rows td.label {
            color: #746a59;
            padding-right: 12px;
        }

        .rows td.value {
            text-align: right;
            font-weight: 600;
        }

        .rows tr.is-due td {
            color: #a34f24;
            font-weight: 700;
        }

        .rows tr.is-total td {
            border-top: 2px solid #e4ddd0;
            border-bottom: none;
            padding-top: 13px;
            font-size: 15px;
            font-weight: 700;
        }

        .notice {
            background: #fef9c3;
            border-radius: 10px;
            padding: 14px 16px;
            margin: 0 0 22px;
            font-size: 14px;
            color: #854d0e;
        }

        .small {
            font-size: 13px;
            color: #746a59;
        }

        .link {
            color: #6d5320;
            text-decoration: underline;
            word-break: break-all;
        }

        .footer {
            padding: 22px 32px 26px;
            border-top: 1px solid #e4ddd0;
            font-size: 13px;
            line-height: 1.7;
            color: #746a59;
        }

        .footer a {
            color: #6d5320;
        }

        @media (max-width: 480px) {

            .brand,
            .content,
            .footer {
                padding-left: 20px;
                padding-right: 20px;
            }

            .panel-code {
                font-size: 30px;
                letter-spacing: 4px;
            }
        }
    </style>
</head>

<body>
    {{-- The line an inbox shows after the subject. Hidden in the email itself. --}}
    <div style="display:none;max-height:0;max-width:0;overflow:hidden;opacity:0;font-size:1px;line-height:1px;color:#f5f0e8;">{{ $preheader ?? '' }}</div>

    <table role="presentation" class="page" width="100%" cellpadding="0" cellspacing="0" border="0">
        <tr>
            <td align="center">
                <table role="presentation" class="sheet" width="560" cellpadding="0" cellspacing="0" border="0"
                    align="center">
                    <tr>
                        <td class="brand">{{ $resort['name'] }}</td>
                    </tr>
                    <tr>
                        <td class="content">
                            <h1>{{ $heading }}</h1>
                            @yield('content')
                        </td>
                    </tr>
                    <tr>
                        <td class="footer">
                            <strong style="color:#2c2416;">{{ $resort['name'] }}</strong><br>
                            @if ($resort['address'])
                                {{ $resort['address'] }}@if ($resort['map_url'])
                                    (<a href="{{ $resort['map_url'] }}">view on a map</a>)
                                @endif
                                <br>
                            @endif
                            @if ($resort['phone'])
                                Call <a href="tel:{{ preg_replace('/[^0-9+]/', '', $resort['phone']) }}">{{ $resort['phone'] }}</a><br>
                            @endif
                            @if ($resort['email'])
                                Email <a href="mailto:{{ $resort['email'] }}">{{ $resort['email'] }}</a><br>
                            @endif
                            @hasSection('footer_note')
                                <div style="margin-top:12px;">@yield('footer_note')</div>
                            @endif
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>

</html>
