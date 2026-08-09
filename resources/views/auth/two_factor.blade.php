@extends('layouts.auth')

@section('title', 'Verify Device — Villa Elena Resort')

@section('auth_bg', 'bg1.png')
@section('auth_tagline', 'Just one more step')

@section('content')

    <h2>Verify it's you</h2>
    <p class="subtitle">We don't recognize this device. Enter the 6-digit code we sent to <strong>{{ $maskedEmail }}</strong>.</p>

    @if(session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif

    <form method="POST" action="{{ route('two-factor.verify') }}">
        @csrf

        <div class="mb-3">
            <label for="code" class="form-label">Verification Code</label>
            <input
                type="text"
                id="code"
                name="code"
                inputmode="numeric"
                autocomplete="one-time-code"
                maxlength="6"
                class="form-control @error('code') is-invalid @enderror"
                placeholder="123456"
                style="letter-spacing:6px;font-size:20px;text-align:center;"
                autofocus
                required
            >
            @error('code')
                <div class="invalid-feedback">{{ $message }}</div>
            @enderror
        </div>

        <button type="submit" class="btn btn-primary-custom">
            <i class="bi bi-shield-check me-2"></i> Verify & Continue
        </button>
    </form>

    <form method="POST" action="{{ route('two-factor.resend') }}" class="mt-3">
        @csrf
        <button type="submit" class="btn btn-link p-0" style="font-size:13px;">Didn't get a code? Resend</button>
    </form>

    <div class="auth-footer">
        <a href="{{ route('login') }}">Back to login</a>
    </div>

@endsection
