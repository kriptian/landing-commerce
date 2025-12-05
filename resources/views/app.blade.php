<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">

        <title inertia>{{ config('app.name', 'Ondigitalsolution') }}</title>
        @php
            use App\Models\Store;
            $defaultFavicon = '/images/New_Logo_ondgtl.png?v=5';
            $favicon = $defaultFavicon;
            
            $buildVersionedUrl = function (?string $url, ?int $version) use ($defaultFavicon) {
                if (empty($url)) {
                    return $defaultFavicon;
                }
                $v = $version ?? 0;
                return $url . (strpos($url, '?') !== false ? '&' : '?') . 'v=' . $v;
            };
            // Intentamos resolver tienda por (1) route-model binding / slug o (2) dominio propio
            try {
                $storeForFavicon = null;
                $routeStore = request()->route('store');
                if ($routeStore instanceof Store) {
                    $storeForFavicon = $routeStore;
                } elseif (is_string($routeStore) && $routeStore !== '') {
                    $storeForFavicon = Store::query()->where('slug', $routeStore)->first(['logo_url','updated_at']);
                }

                if (! $storeForFavicon) {
                    $storeForFavicon = Store::query()->where('custom_domain', request()->getHost())->first(['logo_url','updated_at']);
                }

                if ($storeForFavicon && $storeForFavicon->logo_url) {
                    $favicon = $buildVersionedUrl($storeForFavicon->logo_url, $storeForFavicon->updated_at?->getTimestamp());
                }
            } catch (\Throwable $e) {
                // Silencioso: si falla, usamos el por defecto
            }
        @endphp
        <!-- Favicon/Íconos -->
        <link rel="icon" type="image/png" sizes="32x32" href="{{ $favicon }}">
        <link rel="icon" type="image/png" sizes="16x16" href="{{ $favicon }}">
        <link rel="shortcut icon" type="image/png" href="{{ $favicon }}">
        <link rel="apple-touch-icon" sizes="180x180" href="{{ $favicon }}">

        @if (empty($storeForFavicon))
        <!-- Indicamos explícitamente el /favicon.ico para home/login/landing (para crawlers como Google) -->
        <link rel="icon" type="image/x-icon" href="/favicon.ico">
        @endif

        <!-- Primary Meta Tags -->
        <meta name="title" content="{{ config('app.name', 'Ondigitalsolution') }}">
        <meta name="description" content="Tu tienda online en {{ parse_url(config('app.url'), PHP_URL_HOST) ?? 'ondigitalsolution.com' }}">

        <!-- Open Graph / Facebook / WhatsApp -->
        <meta property="og:type" content="website">
        <meta property="og:url" content="{{ config('app.url') }}">
        <meta property="og:site_name" content="{{ config('app.name', 'Ondigitalsolution') }}">
        <meta property="og:title" content="{{ config('app.name', 'Ondigitalsolution') }}">
        <meta property="og:description" content="Tu tienda online en {{ parse_url(config('app.url'), PHP_URL_HOST) ?? 'ondigitalsolution.com' }}">
        <meta property="og:image" content="{{ config('app.url') }}/images/New_Logo_ondgtl.png?v=5">
        <meta property="og:image:secure_url" content="{{ config('app.url') }}/images/New_Logo_ondgtl.png?v=5">
        <meta property="og:image:width" content="1200">
        <meta property="og:image:height" content="630">

        <!-- Twitter -->
        <meta property="twitter:card" content="summary_large_image">
        <meta property="twitter:url" content="{{ config('app.url') }}">
        <meta property="twitter:title" content="{{ config('app.name', 'Ondigitalsolution') }}">
        <meta property="twitter:description" content="Tu tienda online en {{ parse_url(config('app.url'), PHP_URL_HOST) ?? 'ondigitalsolution.com' }}">
        <meta property="twitter:image" content="{{ config('app.url') }}/images/New_Logo_ondgtl.png?v=5">

        <!-- Structured Data: Organization (logo para Google) -->
        <script type="application/ld+json">
        {!! json_encode([
            '@context' => 'https://schema.org',
            '@type' => 'Organization',
            'name' => config('app.name', 'Ondigitalsolution'),
            'url' => config('app.url'),
            'logo' => config('app.url') . '/images/New_Logo_ondgtl.png?v=5',
        ], JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE) !!}
        </script>

        <!-- Fonts -->
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />

        <!-- CSRF Token -->
        <meta name="csrf-token" content="{{ csrf_token() }}">
        
        <!-- Scripts -->
        @routes
        @vite(['resources/js/app.js', "resources/js/Pages/{$page['component']}.vue"])
        @inertiaHead
    </head>
    <body class="font-sans antialiased">
        @inertia
    </body>
</html>
