<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Villa Elena Resort')</title>

    <!-- Bootstrap 5 -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@500;700&family=Inter:wght@300;400;500;600&display=swap" rel="stylesheet">

    <style>
        * { box-sizing: border-box; }

        body {
            font-family: 'Inter', sans-serif;
            background: linear-gradient(135deg, #0f2027, #203a43, #2c5364);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }

        .auth-wrapper {
            width: 100%;
            max-width: 460px;
        }

        .auth-logo {
            text-align: center;
            margin-bottom: 30px;
        }

        .auth-logo h1 {
            font-family: 'Playfair Display', serif;
            color: #fff;
            font-size: 28px;
            font-weight: 700;
            margin: 0;
            letter-spacing: 0.5px;
        }

        .auth-logo p {
            color: #c49a28;
            font-size: 13px;
            margin: 4px 0 0;
            letter-spacing: 2px;
            text-transform: uppercase;
        }

        .auth-card {
            background: #fff;
            border-radius: 16px;
            padding: 40px;
            box-shadow: 0 25px 60px rgba(0,0,0,0.4);
        }

        .auth-card h2 {
            font-family: 'Playfair Display', serif;
            font-size: 24px;
            color: #1b3a6b;
            margin-bottom: 6px;
        }

        .auth-card .subtitle {
            color: #94a3b8;
            font-size: 14px;
            margin-bottom: 28px;
        }

        .form-label {
            font-size: 13px;
            font-weight: 600;
            color: #374151;
            margin-bottom: 6px;
        }

        .form-control {
            border: 1.5px solid #e5e7eb;
            border-radius: 8px;
            padding: 11px 14px;
            font-size: 14px;
            transition: border-color .2s, box-shadow .2s;
        }

        .form-control:focus {
            border-color: #2e5fa3;
            box-shadow: 0 0 0 3px rgba(46,95,163,0.12);
        }

        .input-group .form-control { border-right: none; }
        .input-group .btn-outline-secondary {
            border: 1.5px solid #e5e7eb;
            border-left: none;
            border-radius: 0 8px 8px 0;
            color: #94a3b8;
            background: #fff;
        }

        .btn-primary-custom {
            background: linear-gradient(135deg, #1b3a6b, #2e5fa3);
            color: #fff;
            border: none;
            border-radius: 8px;
            padding: 12px;
            font-size: 15px;
            font-weight: 600;
            width: 100%;
            transition: opacity .2s, transform .1s;
        }

        .btn-primary-custom:hover {
            opacity: 0.92;
            transform: translateY(-1px);
            color: #fff;
        }

        .divider {
            text-align: center;
            color: #94a3b8;
            font-size: 13px;
            margin: 20px 0;
            position: relative;
        }

        .divider::before, .divider::after {
            content: '';
            position: absolute;
            top: 50%;
            width: 42%;
            height: 1px;
            background: #e5e7eb;
        }
        .divider::before { left: 0; }
        .divider::after  { right: 0; }

        .auth-footer {
            text-align: center;
            margin-top: 24px;
            font-size: 13px;
            color: #64748b;
        }

        .auth-footer a {
            color: #2e5fa3;
            font-weight: 600;
            text-decoration: none;
        }

        .auth-footer a:hover { text-decoration: underline; }

        .is-invalid { border-color: #ef4444 !important; }
        .invalid-feedback { font-size: 12px; color: #ef4444; }

        .alert {
            border-radius: 8px;
            font-size: 13px;
            padding: 10px 14px;
            margin-bottom: 20px;
        }
    </style>
</head>
<body>
    <div class="auth-wrapper">

        <div class="auth-logo">
            <h1>🏝️ Villa Elena</h1>
            <p>Private Rental Resort</p>
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

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
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