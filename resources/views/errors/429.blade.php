@extends('layouts.auth')

@section('title', 'Please Slow Down — Villa Elena Resort')

@section('auth_bg', 'login-bg.jpg')
@section('auth_tagline', 'Your dream getaway awaits')

{{--
    Last-resort page for a rate limit that has no friendlier answer of its
    own. The named limiters in AppServiceProvider send guests back to their
    form with a message instead; this only shows for the plain
    `throttle:N,M` routes, which used to fall through to Laravel's bare
    "429 Too Many Requests" page.
--}}
@php
    $retryAfter = (int) (method_exists($exception, 'getHeaders') ? ($exception->getHeaders()['Retry-After'] ?? 60) : 60);
    $wait = $retryAfter < 60
        ? $retryAfter.' '.\Illuminate\Support\Str::plural('second', $retryAfter)
        : (int) ceil($retryAfter / 60).' '.\Illuminate\Support\Str::plural('minute', (int) ceil($retryAfter / 60));
@endphp

@section('content')

    <h2>Please slow down</h2>

    <p class="subtitle">
        We received too many requests from you in a short time. Please wait
        <strong>{{ $wait }}</strong> and try again.
    </p>

    <a href="{{ url()->previous() !== url()->current() ? url()->previous() : route('home') }}" class="btn btn-primary-custom">
        Go back
    </a>

@endsection
