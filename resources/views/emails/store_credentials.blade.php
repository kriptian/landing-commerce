<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Credenciales de acceso</title>
    <style>
        body { font-family: ui-sans-serif, system-ui, -apple-system, 'Segoe UI', Roboto, 'Helvetica Neue', Arial; color:#111827; }
        .card { max-width: 560px; margin: 0 auto; border:1px solid #e5e7eb; border-radius:12px; padding:24px; }
        .btn { display:inline-block; padding:10px 16px; background:#2563eb; color:#fff; text-decoration:none; border-radius:8px; }
        .muted { color:#6b7280; font-size:14px; }
        .code { background:#f3f4f6; padding:10px; border-radius:8px; font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, 'Liberation Mono', 'Courier New', monospace; }
    </style>
    </head>
<body>
    <div class="card">
        <h2>¡Tu tienda {{ $storeName }} fue creada con éxito!</h2>
        <p>Ya podés ingresar al panel con estas credenciales:</p>
        <div class="code">
            <div><strong>Usuario:</strong> {{ $email }}</div>
            <div><strong>Contraseña:</strong> {{ $plainPassword }}</div>
        </div>
        <p style="margin-top:16px">
            <a href="{{ $loginUrl }}" class="btn">Ir a Iniciar Sesión</a>
        </p>
        <p class="muted">Por seguridad, te recomendamos cambiar la contraseña al ingresar.</p>
    </div>
</body>
</html>


