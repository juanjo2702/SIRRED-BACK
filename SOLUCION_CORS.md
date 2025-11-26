# Solución Definitiva CORS - SIRRED

## El Problema

El error `No 'Access-Control-Allow-Origin' header is present` indica que el servidor web (Apache) no está enviando los headers CORS necesarios en las respuestas a las peticiones OPTIONS (preflight).

## La Solución

### Paso 1: Subir Archivos Actualizados

Sube estos archivos al servidor de producción:

1. **`backend/public/.htaccess`** - Contiene los headers CORS necesarios
2. **`backend/bootstrap/app.php`** - Configuración de Sanctum
3. **`backend/public/test-cors.php`** - Script de diagnóstico (temporal)

### Paso 2: Configurar Variables de Entorno

Edita el archivo `.env` en el servidor y asegúrate de que tenga:

```env
SANCTUM_STATEFUL_DOMAINS=sirred.clubatleticoimperial.com
APP_URL=https://api.sirred.clubatleticoimperial.com
SESSION_DOMAIN=.clubatleticoimperial.com
```

### Paso 3: Verificar Módulos de Apache

Conéctate al servidor por SSH y ejecuta:

```bash
# Verificar si mod_headers está habilitado
apachectl -M | grep headers

# Si no está habilitado, activarlo:
sudo a2enmod headers
sudo systemctl restart apache2
```

### Paso 4: Limpiar Cachés de Laravel

```bash
cd /ruta/al/backend
php artisan config:clear
php artisan cache:clear
php artisan route:clear
php artisan optimize:clear
```

### Paso 5: Verificar Configuración

Accede a: `https://api.sirred.clubatleticoimperial.com/test-cors.php`

Deberías ver un JSON con información sobre la configuración. Verifica que:

-   ✅ `mod_headers` esté habilitado
-   ✅ `SANCTUM_STATEFUL_DOMAINS` esté configurado
-   ✅ El archivo `.htaccess` exista y sea legible

### Paso 6: Probar CORS desde el Navegador

Abre la consola del navegador en `https://sirred.clubatleticoimperial.com` y ejecuta:

```javascript
fetch("https://api.sirred.clubatleticoimperial.com/api/login", {
    method: "OPTIONS",
    headers: {
        Origin: "https://sirred.clubatleticoimperial.com",
        "Access-Control-Request-Method": "POST",
        "Access-Control-Request-Headers": "Content-Type, Authorization",
    },
}).then((r) =>
    console.log("Status:", r.status, "Headers:", [...r.headers.entries()])
);
```

Deberías ver:

-   Status: `200`
-   Headers que incluyan `access-control-allow-origin`

## Contenido del .htaccess Actualizado

El archivo `.htaccess` ahora incluye:

```apache
# CORS Headers - Must be set at Apache level for OPTIONS preflight
Header always set Access-Control-Allow-Origin "https://sirred.clubatleticoimperial.com"
Header always set Access-Control-Allow-Methods "GET, POST, PUT, DELETE, PATCH, OPTIONS"
Header always set Access-Control-Allow-Headers "Content-Type, Authorization, X-Requested-With, Accept, Origin"
Header always set Access-Control-Allow-Credentials "true"
Header always set Access-Control-Max-Age "3600"

# Handle preflight OPTIONS requests immediately
RewriteCond %{REQUEST_METHOD} OPTIONS
RewriteRule ^(.*)$ $1 [R=200,L]
```

## Si Aún No Funciona

### Opción A: Verificar Configuración de Apache Virtual Host

Es posible que necesites agregar la configuración CORS directamente en el archivo de configuración del Virtual Host de Apache.

Edita el archivo de configuración del sitio (usualmente en `/etc/apache2/sites-available/`):

```apache
<VirtualHost *:443>
    ServerName api.sirred.clubatleticoimperial.com

    # ... otras configuraciones ...

    <Directory /ruta/al/backend/public>
        # Habilitar .htaccess
        AllowOverride All

        # CORS Headers
        Header always set Access-Control-Allow-Origin "https://sirred.clubatleticoimperial.com"
        Header always set Access-Control-Allow-Methods "GET, POST, PUT, DELETE, PATCH, OPTIONS"
        Header always set Access-Control-Allow-Headers "Content-Type, Authorization, X-Requested-With, Accept, Origin"
        Header always set Access-Control-Allow-Credentials "true"
    </Directory>
</VirtualHost>
```

Después de editar, reinicia Apache:

```bash
sudo systemctl restart apache2
```

### Opción B: Usar Middleware de Laravel Solamente

Si `mod_headers` no está disponible o no puedes modificar la configuración de Apache, puedes crear un middleware personalizado en Laravel.

Crea `app/Http/Middleware/CorsMiddleware.php`:

```php
<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class CorsMiddleware
{
    public function handle(Request $request, Closure $next)
    {
        if ($request->getMethod() === "OPTIONS") {
            return response('', 200)
                ->header('Access-Control-Allow-Origin', 'https://sirred.clubatleticoimperial.com')
                ->header('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, PATCH, OPTIONS')
                ->header('Access-Control-Allow-Headers', 'Content-Type, Authorization, X-Requested-With, Accept, Origin')
                ->header('Access-Control-Allow-Credentials', 'true')
                ->header('Access-Control-Max-Age', '3600');
        }

        $response = $next($request);

        $response->headers->set('Access-Control-Allow-Origin', 'https://sirred.clubatleticoimperial.com');
        $response->headers->set('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, PATCH, OPTIONS');
        $response->headers->set('Access-Control-Allow-Headers', 'Content-Type, Authorization, X-Requested-With, Accept, Origin');
        $response->headers->set('Access-Control-Allow-Credentials', 'true');

        return $response;
    }
}
```

Y registrarlo en `bootstrap/app.php`:

```php
->withMiddleware(function (Middleware $middleware) {
    $middleware->api(prepend: [
        \App\Http\Middleware\CorsMiddleware::class,
    ]);
})
```

## Resumen de Archivos a Subir

1. ✅ `backend/public/.htaccess`
2. ✅ `backend/bootstrap/app.php`
3. ✅ `backend/public/test-cors.php` (temporal, para diagnóstico)
4. ✅ Actualizar `.env` con `SANCTUM_STATEFUL_DOMAINS`
5. ✅ Ejecutar `php artisan config:clear` en el servidor

## Después de Resolver

Una vez que funcione, puedes eliminar el archivo `test-cors.php` del servidor.
