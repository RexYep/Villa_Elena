{{-- ============================================================ --}}
{{-- FILE: resources/views/auth/forgot_password.blade.php       --}}
{{-- ============================================================ --}}
@extends('layouts.auth')

@section('title', 'Forgot Password — Villa Elena Resort')

@section('content')

    <h2>Reset your password</h2>
    <p class="subtitle">Enter your email and we'll send you a reset link</p>

    <form method="POST" action="{{ route('password.email') }}">
        @csrf

        <div class="mb-4">
            <label for="email" class="form-label">Email Address</label>
            <input
                type="email"
                id="email"
                name="email"
                class="form-control @error('email') is-invalid @enderror"
                value="{{ old('email') }}"
                placeholder="you@example.com"
                autofocus
                required
            >
            @error('email')
                <div class="invalid-feedback">{{ $message }}</div>
            @enderror
        </div>

        <button type="submit" class="btn btn-primary-custom">
            <i class="bi bi-envelope me-2"></i> Send Reset Link
        </button>

    </form>

    <div class="auth-footer">
        Remembered your password? <a href="{{ route('login') }}">Back to login</a>
    </div>

@endsection