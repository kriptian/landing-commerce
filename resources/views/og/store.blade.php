<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title }}</title>
    <meta name="description" content="{{ $description }}">
    <meta property="og:type" content="website">
    <meta property="og:title" content="{{ $title }}">
    <meta property="og:description" content="{{ $description }}">
    <meta property="og:image" content="{{ $image }}">
    <meta property="og:url" content="{{ $url }}">
    <meta property="og:site_name" content="{{ $site_name }}">
    <meta name="twitter:card" content="summary_large_image">
    <link rel="icon" href="{{ $icon }}">
</head>
<body>
    <p><a href="{{ $url }}">Ver el catálogo de {{ $site_name }}</a></p>
</body>
</html>
