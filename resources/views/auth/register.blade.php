@extends('layouts.auth')

@section('title', 'Create Account — Villa Elena Resort')


@section('auth_bg', 'bg1.png')
@section('auth_tagline', 'Start your Villa Elena experience')

@section('content')

    <h1>Create your account</h1>
    <p class="subtitle">Book your dream getaway at Villa Elena</p>

    <form method="POST" action="{{ route('register') }}">
        @csrf

        {{-- Bot checks, see AuthController::registrationBotResponse(). The
             first is the time this form was rendered; the second is a field
             people never see, so anything typed into it came from a script. --}}
        <input type="hidden" name="form_token" value="{{ encrypt(time()) }}">
        <div class="visually-hidden" aria-hidden="true">
            <label for="fax_number">Leave this field empty</label>
            <input type="text" id="fax_number" name="fax_number" value="" tabindex="-1" autocomplete="off">
        </div>

        {{-- Full Name --}}
        <div class="mb-3">
            <label for="full_name" class="form-label">Full Name</label>
            <input
                type="text"
                id="full_name"
                name="full_name"
                class="form-control @error('full_name') is-invalid @enderror"
                value="{{ old('full_name') }}"
                placeholder="Juan dela Cruz"
                autofocus
                required
            >
            @error('full_name')
                <div class="invalid-feedback">{{ $message }}</div>
            @enderror
        </div>

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
                required
            >
            @error('email')
                <div class="invalid-feedback">{{ $message }}</div>
            @enderror
        </div>

        {{-- Phone --}}
        <div class="mb-3">
            <label for="phone" class="form-label">Phone Number</label>
            <input
                type="tel"
                id="phone"
                name="phone"
                maxlength="20"
                class="form-control @error('phone') is-invalid @enderror"
                value="{{ old('phone') }}"
                placeholder="09XX-XXX-XXXX"
                required
            >
            @error('phone')
                <div class="invalid-feedback">{{ $message }}</div>
            @enderror
        </div>

        {{-- Password --}}
        <div class="mb-3">
            <label for="password" class="form-label">Password</label>
            <div class="input-group">
                <input
                    type="password"
                    id="password"
                    name="password"
                    class="form-control @error('password') is-invalid @enderror"
                    autocomplete="new-password" 
                    required
                >
                <button type="button" class="btn btn-outline-secondary" data-pw-toggle="password" aria-label="Show password">
                    <i class="bi bi-eye" aria-hidden="true"></i>
                </button>
                @error('password')
                    <div class="invalid-feedback">{{ $message }}</div>
                @enderror
            </div>
            @include('partials.password_rules', ['for' => 'password'])
        </div>

        {{-- Confirm Password --}}
        <div class="mb-4">
            <label for="password_confirmation" class="form-label">Confirm Password</label>
            <div class="input-group">
                <input
                    type="password"
                    id="password_confirmation"
                    name="password_confirmation"
                    class="form-control"
                    autocomplete="new-password"
                    required
                >
                <button type="button" class="btn btn-outline-secondary" data-pw-toggle="password_confirmation" aria-label="Show password">
                    <i class="bi bi-eye" aria-hidden="true"></i>
                </button>
            </div>
        </div>

        {{-- Submit --}}
        <button type="submit" class="btn btn-primary-custom">
            <i class="bi bi-person-plus me-2"></i> Create Account
        </button>

    </form>

    <div class="auth-footer">
        Already have an account? <a href="{{ route('login') }}">Sign in here</a>
    </div>

    <div class="auth-back">
        <a href="{{ route('home') }}"><i class="bi bi-arrow-left me-1"></i>Back to site</a>
    </div>

@endsection