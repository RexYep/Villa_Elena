<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title', 'Villa Elena Resort')</title>
    @include('partials.favicon')
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@600;700&family=Jost:wght@300;400;500;600&display=swap" rel="stylesheet">
    @vite(['resources/js/payment.js'])
    @stack('styles')
</head>
<body>

@yield('content')

@stack('scripts')
</body>
</html>
