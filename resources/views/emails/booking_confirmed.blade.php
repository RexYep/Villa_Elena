{{-- Ipinapadala sa bawat matagumpay na bayad, sa tatlong anyo (tingnan ang
     BookingConfirmedMail). Ang headline, ang mga pangungusap at ang link ay
     galing sa mailable, dahil pareho ang ipini-print ng plain-text na kopya
     (emails/booking_confirmed_text). Ang balangkas at ang contact ng resort
     ay nasa emails/layout. --}}
@extends('emails.layout')

@section('content')
    <p>Hi {{ $booking->user->first_name ?: 'there' }},</p>

    <p>{{ $opening }}</p>

    <div class="panel">
        <div class="panel-label">Booking reference</div>
        <div class="panel-value">{{ $booking->booking_ref }}</div>
    </div>

    <table role="presentation" class="rows" width="100%" cellpadding="0" cellspacing="0" border="0">
        {{-- Ang slot ang paglalarawan ng booking, hindi ang bilang ng gabi
             (tingnan ang "UI conventions" sa CLAUDE.md). Ang oras ay galing
             sa mga nakaimbak na column, kaya tama pa rin ito sa stay na
             pinahaba ng extendStay(). --}}
        @if ($booking->slot_name)
            <tr>
                <td class="label">Slot</td>
                <td class="value">{{ $booking->slot_name }}</td>
            </tr>
        @endif
        <tr>
            <td class="label">Check-in</td>
            <td class="value">{{ $booking->check_in_date->format('D, M j, Y') }},
                <span style="white-space:nowrap;">{{ $booking->check_in_time_label }}</span>
            </td>
        </tr>
        <tr>
            <td class="label">Check-out</td>
            <td class="value">{{ $booking->check_out_date->format('D, M j, Y') }},
                <span style="white-space:nowrap;">{{ $booking->check_out_time_label }}</span>
            </td>
        </tr>
        <tr>
            <td class="label">Guests</td>
            <td class="value">{{ $booking->num_guests }} guest{{ $booking->num_guests != 1 ? 's' : '' }}</td>
        </tr>
        {{-- Kinuwenta sa BookingConfirmedMail::summary() — doon nakatira
             ang lohika ng "Previously Paid", "Final Payment" at ng promo.
             Ang negatibong halaga ay diskuwento. --}}
        @foreach ($summary['rows'] as [$label, $amount])
            <tr>
                <td class="label">{{ $label }}</td>
                <td class="value">{{ $amount < 0 ? '−' : '' }}₱{{ number_format(abs($amount), 2) }}</td>
            </tr>
        @endforeach
        {{-- Laging ipinapakita, pati ang ₱0.00 kapag bayad na nang buo —
             ang tahasang zero ang sagot sa tanong na "may babayaran pa ba
             ako?". --}}
        <tr class="{{ $summary['balance'] > 0 ? 'is-due' : '' }}">
            <td class="label">Balance Remaining</td>
            <td class="value">₱{{ number_format($summary['balance'], 2) }}</td>
        </tr>
        <tr class="is-total">
            <td>Payment Status</td>
            <td class="value" style="color:{{ $summary['fully_paid'] ? '#15803d' : '#a34f24' }};">
                {{ $summary['status_label'] }}</td>
        </tr>
    </table>

    @if ($balanceNote)
        <div class="notice">{{ $balanceNote }}</div>
    @endif

    <p style="margin:6px 0 8px;">
        <a href="{{ $bookingUrl }}"
            style="display:inline-block;background:#2c2416;color:#ffffff;text-decoration:none;font-size:15px;font-weight:700;padding:13px 26px;border-radius:8px;">View
            your booking</a>
    </p>
    <p class="small">You will be asked to sign in first.</p>

    @foreach ($policyLines as $line)
        <p class="small">{{ $line }}</p>
    @endforeach

    <p class="small" style="margin-bottom:0;">
        Questions about your booking? Reach us using the details below.
    </p>
@endsection

@section('footer_note')
    You are receiving this because booking emails are switched on for your account. You can turn them off
    under Settings ---> Booking emails. Sign-in and password emails are always sent.
@endsection

