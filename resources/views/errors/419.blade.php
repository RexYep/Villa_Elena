@extends('layouts.auth')

@section('title', 'This page expired — Villa Elena Resort')

@section('auth_bg', 'login-bg.jpg')
@section('auth_tagline', 'Your dream getaway awaits')

@section('content')

    <h1>This page expired</h1>

    <p class="subtitle">
        For your security, the form timed out after sitting open too long. Nothing was submitted. Go back, refresh the page and try again.
    </p>

    <a href="{{ url()->previous() !== url()->current() ? url()->previous() : url('/') }}" class="btn btn-primary-custom">
        Go back
    </a>

@endsection
