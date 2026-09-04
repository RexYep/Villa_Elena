@extends('layouts.auth')

@section('title', 'Verify Your Email — Villa Elena Resort')

@section('auth_bg', 'login-bg.jpg')
@section('auth_tagline', 'One more step to complete your booking')

@section('content')

    <h2>Verify your email</h2>

    {{--
        This page is reached two different ways, and it must not describe
        them identically. Registration and "resend" DO send a link, and
        flash verification_sent to say whether that send worked. But every
        login by an unverified user lands here too, and that path sends
        NOTHING — it only renders the page. Claiming "we sent a link" there
        sent someone hunting through an inbox for a message that was never
        sent on that visit, from an account registered weeks earlier.
    --}}
    @if (session('verification_sent') === true)
        <p class="subtitle">
            We just sent a verification link to <strong>{{ Auth::user()->email }}</strong>.
            Please click that link to activate your account.
        </p>
    @elseif (session('verification_sent') === false)
        <p class="subtitle">
            Your account was created, but the verification link could not be sent to
            <strong>{{ Auth::user()->email }}</strong> just now. Nothing is wrong with
            your account — please request a new link below.
        </p>
    @else
        <p class="subtitle">
            Your account isn't activated yet. A verification link was emailed to
            <strong>{{ Auth::user()->email }}</strong> when you registered, which may
            have been a while ago. <strong>No new email was sent just now</strong> — if
            the original never arrived, request a fresh link below.
        </p>
    @endif

    <p>
        Check your spam folder first — the link may already be sitting there.
    </p>

    <form method="POST" action="{{ route('verification.send') }}">
        @csrf
        <button type="submit" class="btn btn-primary-custom">
            <i class="bi bi-envelope-arrow-up me-2"></i> Resend Verification Email
        </button>
    </form>

    <form method="POST" action="{{ route('logout') }}" class="mt-3">
        @csrf
        <button type="submit" class="btn btn-link">Back</button>
    </form>

@endsection