@extends('layouts.auth')

@section('title', 'You can't open this page — Villa Elena Resort')

@section('auth_bg', 'login-bg.jpg')
@section('auth_tagline', 'Your dream getaway awaits')

@section('content')

    <h2>You can't open this page</h2>

    <p class="subtitle">
        Your account doesn't have access to this page. If you think that's a mistake, sign in with the right account or contact the resort.
    </p>

    <a href="{{ url('/') }}" class="btn btn-primary-custom">
        Back to home
    </a>

@endsection
