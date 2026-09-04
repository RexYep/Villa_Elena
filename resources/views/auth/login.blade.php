@extends('layouts.auth')

@section('title', 'Login — Villa Elena Resort')


@section('auth_bg', 'bg1.png')
@section('auth_tagline', 'Welcome back to paradise')

@section('content')

    <h2>Welcome back</h2>
    <p class="subtitle">Sign in to your account to continue</p>

    <form method="POST" action="{{ route('login') }}">
        @csrf

        {{-- Email --}}
        <div class="mb-3">
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

        {{-- Password --}}
        <div class="mb-3">
            <label for="password" class="form-label">
                Password
                <a href="{{ route('password.request') }}" class="float-end fw-normal" style="color:#2e5fa3; font-size:12px;">
                    Forgot password?
                </a>
            </label>
            <div class="input-group">
                <input
                    type="password"
                    id="password"
                    name="password"
                    class="form-control @error('password') is-invalid @enderror"
                    placeholder="Enter your password"
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

        {{-- Remember Me --}}
        <div class="mb-4">
            <div class="form-check">
                <input class="form-check-input" type="checkbox" name="remember" id="remember">
                <label class="form-check-label" for="remember" style="font-size:13px; color:#64748b;">
                    Keep me signed in
                </label>
            </div>
        </div>

        {{-- Submit --}}
        <button type="submit" class="btn btn-primary-custom">
            <i class="bi bi-box-arrow-in-right me-2"></i> Sign In
        </button>

    </form>

    <div class="auth-footer">
        Don't have an account? <a href="{{ route('register') }}">Create one now</a>
    </div>

    <div class="auth-back">
        <a href="{{ route('home') }}"><i class="bi bi-arrow-left me-1"></i>Back to site</a>
    </div>

@endsection