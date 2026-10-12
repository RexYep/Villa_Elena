@extends('emails.layout_text')

@section('content')
Hi {!! $booking->user->first_name ?: 'there' !!},

{!! $body !!}

Refund amount: ₱{!! number_format($amount, 2) !!}
Booking: {!! $booking->booking_ref !!}

@if ($actionNote)
{!! $actionNote !!}

@endif
{!! $actionLabel !!} (you will be asked to sign in first):
{!! $actionUrl !!}
@endsection

@section('footer_note')
You are receiving this because booking emails are switched on for your account. You can turn them off under My Account, Booking emails. Sign-in and password emails are always sent.
@endsection
