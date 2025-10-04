<p align="center"><a href="https://laravel.com" target="_blank"><img src="https://raw.githubusercontent.com/laravel/art/master/logo-lockup/5%20SVG/2%20CMYK/1%20Full%20Color/laravel-logolockup-cmyk-red.svg" width="400" alt="Laravel Logo"></a></p>

<p align="center">
<a href="https://github.com/laravel/framework/actions"><img src="https://github.com/laravel/framework/workflows/tests/badge.svg" alt="Build Status"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/dt/laravel/framework" alt="Total Downloads"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/v/laravel/framework" alt="Latest Stable Version"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/l/laravel/framework" alt="License"></a>
</p>

## Deploy y Rollback (Producción en Hostinger)

Guía breve y comprobada para desplegar y volver atrás si es necesario.

### Archivos requeridos en el servidor

El dominio principal de Hostinger apunta a `public_html`, por lo que se necesitan estos `.htaccess`:

- `public_html/.htaccess` (puente a `public/`):
  ```apache
  <IfModule mod_rewrite.c>
    RewriteEngine On
    RewriteRule ^$ public/ [L]
    RewriteRule (.*) public/$1 [L]
  </IfModule>
  ```

- `public/.htaccess` (Laravel):
  ```apache
  <IfModule mod_rewrite.c>
    <IfModule mod_negotiation.c>
      Options -MultiViews -Indexes
    </IfModule>
    RewriteEngine On
    RewriteCond %{REQUEST_FILENAME} !-d
    RewriteCond %{REQUEST_FILENAME} !-f
    RewriteRule ^ index.php [L]
  </IfModule>
  ```

Si llegaran a borrarse en el servidor, recrearlos con este contenido y limpiar cachés de Laravel.

### Deploy seguro

1) En el servidor (SSH), dentro de `public_html`:
   ```bash
   bash deploy.sh "mi-secreto-deploy"
   ```
   Esto pone el sitio en mantenimiento con secreto, sincroniza `origin/main`, ejecuta `composer install --no-dev`, migra (si hay), limpia y recachea rutas y config, e intenta `opcache_reset()`.

2) Verificar rápidamente:
   - `php artisan route:list | grep catalogo.index`
   - Navegar el sitio. Si hay CDN, purgar cache de la home y `build/manifest.json`.

### Rollback a un commit estable

```bash
php artisan down --secret="restore"
git reset --hard <SHA_ESTABLE>
php artisan optimize:clear && php artisan route:cache && php artisan config:cache
php artisan up
```

Si aparece 403, recrear los `.htaccess` anteriores y repetir el paso de limpieza de cachés.

### Restaurar el entorno local al estado de producción

```bash
git stash -u -m "backup-pre-restore"   # opcional
git checkout main
git reset --hard ddaa7a8                # SHA estable
git clean -fd
# (Opcional) forzar remoto a ese punto
git push --force-with-lease origin main
```

Para recuperar el stash: `git stash apply stash@{0}`. Para eliminarlo: `git stash drop stash@{0}`.

### Flujo recomendado

- Trabajar en ramas de feature y abrir PR a `main`.
- Desplegar solo desde `main` con `deploy.sh`.
- Crear tags para puntos estables:
  ```bash
  git tag -a prod-estable-YYYY-MM-DD-HHMM -m "punto estable"
  git push --tags
  ```

## Notas de entorno local

Si `php artisan optimize:clear` falla por conexión MySQL en local (host no resuelve), usar `CACHE_DRIVER=file`. Para desarrollo simple puede usarse SQLite:

```env
DB_CONNECTION=sqlite
# y crear el archivo
touch database/database.sqlite
```

## Agradecimientos

Este proyecto se basa en Laravel + Inertia + Vue. Gracias a la comunidad por las herramientas y documentación.

## Contributing

Thank you for considering contributing to the Laravel framework! The contribution guide can be found in the [Laravel documentation](https://laravel.com/docs/contributions).

## Code of Conduct

In order to ensure that the Laravel community is welcoming to all, please review and abide by the [Code of Conduct](https://laravel.com/docs/contributions#code-of-conduct).

## Security Vulnerabilities

If you discover a security vulnerability within Laravel, please send an e-mail to Taylor Otwell via [taylor@laravel.com](mailto:taylor@laravel.com). All security vulnerabilities will be promptly addressed.

## License

The Laravel framework is open-sourced software licensed under the [MIT license](https://opensource.org/licenses/MIT).
