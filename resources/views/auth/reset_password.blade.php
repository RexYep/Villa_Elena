@extends('layouts.auth')

@section('title', 'Set New Password — Villa Elena Resort')

@section('auth_bg', 'bg1.png')
@section('auth_tagline', 'Almost there — set your new password')

@section('content')

    <h2>Set new password</h2>
    <p class="subtitle">Choose a strong password for your account</p>

    <form method="POST" action="{{ route('password.update') }}">
        @csrf

        <input type="hidden" name="token" value="{{ $token }}">

        <div class="mb-3">
            <label for="email" class="form-label">Email Address</label>
            <input
                type="email"
                id="email"
                name="email"
                class="form-control @error('email') is-invalid @enderror"
                value="{{ old('email', $email) }}"
                required
            >
            @error('email')
                <div class="invalid-feedback">{{ $message }}</div>
            @enderror
        </div>

        <div class="mb-3">
            <label for="password" class="form-label">New Password</label>
            <div class="input-group">
                <input
                    type="password"
                    id="password"
                    name="password"
                    class="form-control @error('password') is-invalid @enderror"
                    placeholder="Min. 8 characters"
                    required
                >
                <button type="button" class="btn btn-outline-secondary" onclick="togglePassword('password', this)">
                    <i class="bi bi-eye"></i>
                </button>
                @error('password')
                    <div class="invalid-feedback">{{ $message }}</div>
                @enderror
            </div>
        </div>

        <div class="mb-4">
            <label for="password_confirmation" class="form-label">Confirm New Password</label>
            <div class="input-group">
                <input
                    type="password"
                    id="password_confirmation"
                    name="password_confirmation"
                    class="form-control"
                    placeholder="Repeat your new password"
                    required
                >
                <button type="button" class="btn btn-outline-secondary" onclick="togglePassword('password_confirmation', this)">
                    <i class="bi bi-eye"></i>
                </button>
            </div>
        </div>

        <button type="submit" class="btn btn-primary-custom">
            <i class="bi bi-shield-check me-2"></i> Reset Password
        </button>

    </form>

    {{-- This page is reached by clicking a link in an email, so it has no
         page to go "back" to in the browser sense — without this it was the
         one auth page with no way out at all. Someone who opens the link and
         then remembers their password, or realises it's for the wrong
         account, would otherwise have to edit the URL by hand. --}}
    <div class="auth-footer">
        Remembered your password? <a href="{{ route('login') }}">Back to login</a>
    </div>

@endsection