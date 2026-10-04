<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    {{-- Kailangan ito ng private-channel authorization ng payment status
         watcher — POST ang /broadcasting/auth, kaya wala itong CSRF token
         na mahuhugot kung wala ang meta na ito. --}}
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Villa Elena Resort')</title>
    @include('partials.favicon')
    @include('partials.fonts')
    @vite(['resources/js/payment.js'])
    @stack('styles')
</head>
<body>

@yield('content')

@stack('scripts')
</body>
</html>
