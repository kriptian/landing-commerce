<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">

        <title inertia>{{ config('app.name', 'Ondigitalsolution') }}</title>
        <link rel="icon" type="image/png" href="/images/digitalsolution-logo.png">

        <!-- Primary Meta Tags -->
        <meta name="title" content="{{ config('app.name', 'Ondigitalsolution') }}">
        <meta name="description" content="Tu tienda online en {{ parse_url(config('app.url'), PHP_URL_HOST) ?? 'ondigitalsolution.com' }}">

        <!-- Open Graph / Facebook / WhatsApp -->
        <meta property="og:type" content="website">
        <meta property="og:url" content="{{ config('app.url') }}">
        <meta property="og:site_name" content="{{ config('app.name', 'Ondigitalsolution') }}">
        <meta property="og:title" content="{{ config('app.name', 'Ondigitalsolution') }}">
        <meta property="og:description" content="Tu tienda online en {{ parse_url(config('app.url'), PHP_URL_HOST) ?? 'ondigitalsolution.com' }}">
        <meta property="og:image" content="{{ config('app.url') }}/images/digitalsolution-logo.png">
        <meta property="og:image:secure_url" content="{{ config('app.url') }}/images/digitalsolution-logo.png">
        <meta property="og:image:width" content="1200">
        <meta property="og:image:height" content="630">

        <!-- Twitter -->
        <meta property="twitter:card" content="summary_large_image">
        <meta property="twitter:url" content="{{ config('app.url') }}">
        <meta property="twitter:title" content="{{ config('app.name', 'Ondigitalsolution') }}">
        <meta property="twitter:description" content="Tu tienda online en {{ parse_url(config('app.url'), PHP_URL_HOST) ?? 'ondigitalsolution.com' }}">
        <meta property="twitter:image" content="{{ config('app.url') }}/images/digitalsolution-logo.png">

        <!-- Fonts -->
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />

        <!-- Scripts -->
        @routes
        @vite(['resources/js/app.js', "resources/js/Pages/{$page['component']}.vue"])
        @inertiaHead
    </head>
    <body class="font-sans antialiased">
        @inertia
    </body>
</html>
