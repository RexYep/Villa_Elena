<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title', 'Villa Elena Resort')</title>
    <link rel="icon" href="{{ asset('favicon.ico') }}" sizes="any">
    <link rel="apple-touch-icon" href="{{ asset('apple-touch-icon.png') }}">
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:ital,wght@0,400;0,600;0,700;1,400&family=Jost:wght@300;400;500;600&display=swap" rel="stylesheet">
    @vite(['resources/js/portal.js'])
    @stack('styles')
</head>
<body>

@php($isBare = trim($__env->yieldContent('bare')) !== '' || $__env->hasSection('bare'))
@php($noChatbot = trim($__env->yieldContent('no-chatbot')) !== '' || $__env->hasSection('no-chatbot'))

@if($isBare)
    @yield('content')
@else
    @hasSection('nav')
        @yield('nav')
    @else
        <nav class="nav">
            <a href="{{ route('home') }}" class="nav-brand">Villa <em>Elena</em></a>
            <div class="nav-right">
                @auth
                    <a href="{{ route('customer.home') }}" class="nav-btn nav-btn-ghost">My Dashboard</a>
                @else
                    <a href="{{ route('login') }}" class="nav-btn nav-btn-ghost">Sign In</a>
                    <a href="{{ route('register') }}" class="nav-btn nav-btn-gold">Register</a>
                @endauth
            </div>
        </nav>
    @endif

    <main class="main">
    @yield('content')
    </main>

    @yield('footer')
@endif

@stack('scripts')
@if(!$noChatbot)
    @include('partials.chatbot')
@endif
</body>
</html>
