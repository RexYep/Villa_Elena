@extends('emails.layout_text')

@section('content')
Hi {!! $booking->user->first_name ?: 'there' !!},

{!! $body !!}

Booking reference: {!! $booking->booking_ref !!}
@foreach ($rows as $row)
{!! $row[0] !!}: {!! $row[1] !!}
@endforeach

@if ($note)
{!! $note !!}

@endif
View your booking (you will be asked to sign in first):
{!! $actionUrl !!}
@endsection

@section('footer_note')
You are receiving this because booking emails are switched on for your account. You can turn them off under Settings ---> Booking emails. Sign-in and password emails are always sent.
@endsection
