@extends('layouts.auth')

@section('title', 'Verify Your Email — Villa Elena Resort')

@section('auth_bg', 'login-bg.jpg')
@section('auth_tagline', 'One more step to complete your booking')

@section('content')

    <h2>Verify your email</h2>
    <p class="subtitle">
        We sent a verification link to <strong>{{ auth()->user()->email }}</strong>.
        Please click that link to activate your account.
    </p>

    @if (session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif

    <p>
        Didn't get the email? Check your spam folder, or request a new link below.
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