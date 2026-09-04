<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Villa Elena Resort')</title>
    @include('partials.favicon')

    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@500;700&family=Inter:wght@300;400;500;600&display=swap" rel="stylesheet">
    @vite(['resources/js/auth.js'])
</head>
<body>
    <div class="auth-container">

        {{-- ========== LEFT: Background Image Panel ========== --}}
        <div class="auth-image-panel" style="background-image: url('{{ asset('images/' . View::yieldContent('auth_bg', 'login-bg.jpg')) }}');">
            <div class="auth-image-overlay"></div>
            <div class="auth-image-content">
                {{-- The wordmark is the way back to the public site. Every auth
                     page gets it for free this way, without each one growing a
                     "back" link that would compete with its own ("Back to
                     login", which points somewhere else entirely). --}}
                <a href="{{ route('home') }}" class="auth-brand-link">
                    <h1><img src="{{ asset('images/logo.png') }}" alt="" class="brand-mark"> Villa Elena</h1>
                    <p>Private Rental Resort</p>
                </a>
                <span class="auth-image-tagline">@yield('auth_tagline', 'Your dream getaway awaits')</span>
            </div>
        </div>

        {{-- ========== RIGHT: Form Panel ========== --}}
        <div class="auth-form-panel">
            <div class="auth-form-inner">

                {{-- Mobile-only logo (hidden on desktop where the image panel shows it).
                     Links home for the same reason the desktop one does — on a phone
                     the image panel is gone, so this is the only wordmark there is. --}}
                <div class="auth-logo-mobile">
                    <a href="{{ route('home') }}" class="auth-brand-link">
                        <h1><img src="{{ asset('images/logo.png') }}" alt="" class="brand-mark"> Villa Elena</h1>
                        <p>Private Rental Resort</p>
                    </a>
                </div>

                <div class="auth-card">

                    {{-- Flash Messages --}}
                    @if(session('success'))
                        <div class="alert alert-success">
                            <i class="bi bi-check-circle me-2"></i>{{ session('success') }}
                        </div>
                    @endif

                    @if(session('error'))
                        <div class="alert alert-danger">
                            <i class="bi bi-exclamation-circle me-2"></i>{{ session('error') }}
                        </div>
                    @endif

                    @yield('content')

                </div>

            </div>
        </div>

    </div>

    <script>
        // Toggle password visibility
        function togglePassword(inputId, btn) {
            const input = document.getElementById(inputId);
            const icon  = btn.querySelector('i');
            if (input.type === 'password') {
                input.type = 'text';
                icon.classList.replace('bi-eye', 'bi-eye-slash');
            } else {
                input.type = 'password';
                icon.classList.replace('bi-eye-slash', 'bi-eye');
            }
        }
    </script>
</body>
</html>