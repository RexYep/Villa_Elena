{{-- Ang email na katapat ng isang abiso tungkol sa refund (v7.68). Ang
     headline at ang pangungusap ay ibinibigay ng NotificationHelper, kaya
     pareho ang sinasabi nito at ng abiso sa kampana — huwag magsulat ng
     pananalita tungkol sa refund dito. Ang balangkas at ang contact ng
     resort ay nasa emails/layout. --}}
@extends('emails.layout')

@section('content')
    <p>Hi {{ $booking->user->first_name ?: 'there' }},</p>

    <p>{{ $body }}</p>

    <div class="panel">
        <div class="panel-label">Refund amount</div>
        <div class="panel-value">₱{{ number_format($amount, 2) }}</div>
        <div class="panel-sub">Booking {{ $booking->booking_ref }}</div>
    </div>

    @if ($actionNote)
        <div class="notice">{{ $actionNote }}</div>
    @endif

    <p style="margin:6px 0 8px;">
        <a href="{{ $actionUrl }}"
            style="display:inline-block;background:#2c2416;color:#ffffff;text-decoration:none;font-size:15px;font-weight:700;padding:13px 26px;border-radius:8px;">{{ $actionLabel }}</a>
    </p>
    <p class="small">You will be asked to sign in first.</p>

    <p class="small" style="margin-bottom:0;">
        Questions about this refund? Reach us using the details below.
    </p>
@endsection

@section('footer_note')
    You are receiving this because booking emails are switched on for your account. You can turn them off
    under My Account, Booking emails. Sign-in and password emails are always sent.
@endsection
