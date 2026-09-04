<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Villa Elena — Under Maintenance</title>
    @include('partials.favicon')
    <link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:wght@600;700&family=DM+Sans:wght@400;500&display=swap" rel="stylesheet">
    <style>
        * { box-sizing: border-box; }
        body {
            margin: 0;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            background: #fdfbf7;
            color: #2c2416;
            font-family: 'DM Sans', sans-serif;
            text-align: center;
            padding: 24px;
        }
        .box {
            max-width: 480px;
        }
        .icon {
            width: 72px;
            height: 72px;
            margin-bottom: 16px;
        }
        h1 {
            font-family: 'Cormorant Garamond', serif;
            font-size: 32px;
            font-weight: 700;
            margin: 0 0 12px;
        }
        p {
            font-size: 15px;
            line-height: 1.6;
            color: #6b6152;
            margin: 0;
        }
    </style>
</head>
<body>
    <div class="box">
        <img src="{{ asset('images/logo.png') }}" alt="Villa Elena" class="icon">
        <h1>We'll be right back</h1>
        <p>Villa Elena is currently undergoing maintenance. Please check back shortly — we appreciate your patience.</p>
    </div>
</body>
</html>
