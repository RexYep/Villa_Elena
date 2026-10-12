{{-- The email beside a notice that a booking changed without a payment: an
     unpaid hold ran out, a cancellation, or a move to another date (v7.74).
     The headline and the sentence come from NotificationHelper, so this says
     what the bell says; do not write wording about the change here. The frame
     and the resort's contact lines are in emails/layout. --}}
@extends('emails.layout')

@section('content')
    <p>Hi {{ $booking->user->first_name ?: 'there' }},</p>

    <p>{{ $body }}</p>

    <div class="panel">
        <div class="panel-label">Booking reference</div>
        <div class="panel-value">{{ $booking->booking_ref }}</div>
    </div>

    @if ($rows)
        <table role="presentation" class="rows" width="100%" cellpadding="0" cellspacing="0" border="0">
            @foreach ($rows as $row)
                <tr class="{{ $row[2] ?? false ? 'is-due' : '' }}">
                    <td class="label">{{ $row[0] }}</td>
                    <td class="value">{{ $row[1] }}</td>
                </tr>
            @endforeach
        </table>
    @endif

    @if ($note)
        <div class="notice">{{ $note }}</div>
    @endif

    <p style="margin:6px 0 8px;">
        <a href="{{ $actionUrl }}"
            style="display:inline-block;background:#2c2416;color:#ffffff;text-decoration:none;font-size:15px;font-weight:700;padding:13px 26px;border-radius:8px;">View
            your booking</a>
    </p>
    <p class="small">You will be asked to sign in first.</p>

    <p class="small" style="margin-bottom:0;">
        Questions about your booking? Reach us using the details below.
    </p>
@endsection

@section('footer_note')
    You are receiving this because booking emails are switched on for your account. You can turn them off
    under Settings ---> Booking emails. Sign-in and password emails are always sent.
@endsection

