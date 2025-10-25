<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Cambio de plan</title>
    <style>
        body { font-family: ui-sans-serif, system-ui, -apple-system, 'Segoe UI', Roboto, 'Helvetica Neue', Arial; color:#111827; }
        .card { max-width: 560px; margin: 0 auto; border:1px solid #e5e7eb; border-radius:12px; padding:24px; }
        .muted { color:#6b7280; font-size:14px; }
    </style>
</head>
<body>
    <div class="card">
        <h2>¡Tu plan fue actualizado!</h2>
        <p>Acabas de cambiar el plan de tu tienda <strong>{{ $storeName }}</strong> de <strong>{{ ucfirst($from) }}</strong> a <strong>{{ ucfirst($to) }}</strong>.</p>
        <p class="muted">Este cambio puede tener costo adicional. Un experto te contactará para acompañarte.</p>
    </div>
</body>
</html>


