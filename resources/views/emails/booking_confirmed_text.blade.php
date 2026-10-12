@extends('emails.layout_text')

@section('content')
Hi {!! $booking->user->first_name ?: 'there' !!},

{!! $opening !!}

Booking reference: {!! $booking->booking_ref !!}

@if ($booking->slot_name)
Slot: {!! $booking->slot_name !!}
@endif
Check-in: {!! $booking->check_in_date->format('D, M j, Y') !!}, {!! $booking->check_in_time_label !!}
Check-out: {!! $booking->check_out_date->format('D, M j, Y') !!}, {!! $booking->check_out_time_label !!}
Guests: {!! $booking->num_guests !!}

@foreach ($summary['rows'] as [$label, $amount])
{!! $label !!}: {!! $amount < 0 ? '-' : '' !!}₱{!! number_format(abs($amount), 2) !!}
@endforeach
Balance Remaining: ₱{!! number_format($summary['balance'], 2) !!}
Payment Status: {!! $summary['status_label'] !!}

@if ($balanceNote)
{!! $balanceNote !!}

@endif
View your booking (you will be asked to sign in first):
{!! $bookingUrl !!}
@foreach ($policyLines as $line)

{!! $line !!}
@endforeach
@endsection

@section('footer_note')
You are receiving this because booking emails are switched on for your account. You can turn them off under My Account, Booking emails. Sign-in and password emails are always sent.
@endsection
