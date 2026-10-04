@extends('layouts.auth')

@section('title', 'Something went wrong — Villa Elena Resort')

@section('auth_bg', 'login-bg.jpg')
@section('auth_tagline', 'Your dream getaway awaits')

@section('content')

    <h1>Something went wrong</h1>

    <p class="subtitle">
        That's a problem on our side, not yours. Please try again in a moment. If it keeps happening, contact the resort.
    </p>

    <a href="{{ url('/') }}" class="btn btn-primary-custom">
        Back to home
    </a>

@endsection
