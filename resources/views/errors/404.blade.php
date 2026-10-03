@extends('layouts.auth')

@section('title', 'Page not found — Villa Elena Resort')

@section('auth_bg', 'login-bg.jpg')
@section('auth_tagline', 'Your dream getaway awaits')

@section('content')

    <h2>Page not found</h2>

    <p class="subtitle">
        We couldn't find the page you were looking for. The link may be old, or the address may have a typo.
    </p>

    <a href="{{ url('/') }}" class="btn btn-primary-custom">
        Back to home
    </a>

@endsection
