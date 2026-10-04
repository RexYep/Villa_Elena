<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title', 'Villa Elena Resort')</title>
    @include('partials.favicon')
    @include('partials.fonts')
    @vite(['resources/js/portal.js'])
    @stack('styles')
</head>
<body>

@php($isBare = trim($__env->yieldContent('bare')) !== '' || $__env->hasSection('bare'))
@php($noChatbot = trim($__env->yieldContent('no-chatbot')) !== '' || $__env->hasSection('no-chatbot'))
{{-- Tingnan ang User::homeRouteName(). Isang-linya ang anyo nito nang sadya,
     katulad ng dalawa sa itaas: ang block form ay nilalamon ng compiler kapag
     may isang-linyang anyo sa iisang file (CLAUDE.md). Huwag ding isulat ang
     pangalan ng directive kahit sa loob ng komentong ito — kinukompile pa rin
     ito, at iyon mismo ang bumasag sa file na ito habang isinusulat. --}}
@php($portalHomeRoute = auth()->user()?->homeRouteName())

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
                    @if ($portalHomeRoute)
                        <a href="{{ route($portalHomeRoute) }}"
                            class="nav-btn nav-btn-ghost">{{ auth()->user()->homeLabel() }}</a>
                    @endif
                    <form method="POST" action="{{ route('logout') }}" class="m-0">
                        @csrf
                        <button type="submit" class="nav-btn nav-btn-ghost">Sign Out</button>
                    </form>
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
