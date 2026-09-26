@extends('layouts.auth')

@section('title', 'Check Your Email — Villa Elena Resort')

@section('auth_bg', 'login-bg.jpg')
@section('auth_tagline', 'One more step to complete your booking')

@section('content')

    <h2>Check your email</h2>

    {{--
        THIS PAGE MUST NOT KNOW WHICH BRANCH SENT IT HERE.

        register() reaches it two ways: a new account was created and a
        verification link was sent, or the address already belonged to
        somebody and they were told about the attempt instead. The whole
        point of the change is that the two are indistinguishable from
        outside, so nothing here may name the address, count anything,
        or hint at which happened. Do not add a resend button either —
        it would need to know whose account to resend for.
    --}}
    <p class="subtitle">
        If everything checks out, a message is on its way with a link to finish setting up your
        account. Please look in your inbox — and in your spam folder, since a first message from
        us sometimes lands there.
    </p>

    <p class="subtitle">
        Already have an account? Just sign in as usual.
    </p>

    <div class="mt-4">
        <a href="{{ route('login') }}" class="btn btn-primary-custom">
            <i class="bi bi-box-arrow-in-right me-2"></i> Go to sign in
        </a>
    </div>

    <div class="auth-footer mt-3">
        Forgotten your password? <a href="{{ route('password.request') }}">Reset it here</a>.
    </div>

@endsection
