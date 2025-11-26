# Configuración de Producción - SIRRED Backend

## Cambios Realizados para Resolver CORS

### 1. Modificación de `bootstrap/app.php`

Se cambió de usar `HandleCors::class` manualmente a usar `statefulApi()`, que es el método recomendado para APIs con Sanctum.

**Antes:**

```php
->withMiddleware(function (Middleware $middleware): void {
    $middleware->api(prepend: [
        \Illuminate\Http\Middleware\HandleCors::class,
    ]);
})
```

**Ahora:**

```php
->withMiddleware(function (Illuminate\Foundation\Configuration\Middleware $middleware) {
    $middleware->statefulApi(); // activa Sanctum para API
})
```

### 2. Variables de Entorno Requeridas

Debes agregar esta línea a tu archivo `.env` en el servidor de producción:

```env
SANCTUM_STATEFUL_DOMAINS=sirred.clubatleticoimperial.com
```

**Nota:** No incluyas `https://` en el dominio, solo el nombre del dominio.

### 3. Archivo `.htaccess`

El archivo `.htaccess` ya no necesita headers CORS porque Laravel los maneja automáticamente.
El archivo actual solo debe tener las reglas de rewrite básicas.

### 4. Archivo `config/cors.php`

Este archivo puede permanecer como está, pero con `statefulApi()`, Sanctum maneja CORS automáticamente
para los dominios listados en `SANCTUM_STATEFUL_DOMAINS`.

## Pasos para Desplegar en Producción

### Paso 1: Actualizar Backend

1. **Subir los archivos modificados al servidor:**

    - `bootstrap/app.php`
    - `public/.htaccess`

2. **Editar el archivo `.env` en el servidor de producción:**

    ```bash
    # Conectarse al servidor y editar .env
    nano .env
    ```

    **Agregar esta línea:**

    ```env
    SANCTUM_STATEFUL_DOMAINS=sirred.clubatleticoimperial.com
    ```

3. **Limpiar cachés en el servidor:**
    ```bash
    cd /ruta/al/backend
    php artisan config:clear
    php artisan cache:clear
    php artisan route:clear
    ```

### Paso 2: Actualizar Frontend

1. **Reconstruir el frontend con las nuevas configuraciones:**

    ```bash
    cd frontend
    npm run build
    ```

2. **Subir la carpeta `dist/spa` al servidor de hosting del frontend**
    - La carpeta `dist/spa` contiene todos los archivos compilados
    - Subir todo el contenido a `https://sirred.clubatleticoimperial.com`

### Paso 3: Verificar Configuración

Asegúrate de que el archivo `.env` del frontend en producción tenga:

```env
VITE_API_URL=https://api.sirred.clubatleticoimperial.com/api
```

**Nota:** Si estás usando un servicio de hosting que construye automáticamente (como Vercel, Netlify),
configura esta variable de entorno en el panel de control del servicio.

## Verificación

Después de desplegar, verifica que:

-   ✅ El login funciona desde `https://sirred.clubatleticoimperial.com`
-   ✅ No hay errores CORS en la consola del navegador
-   ✅ Las peticiones a la API se completan correctamente

## Comparación con SRIPI

Esta configuración es idéntica a la que funciona en SRIPI:

-   Usa `statefulApi()` en lugar de configuración manual de CORS
-   No tiene headers CORS en `.htaccess`
-   Confía en la configuración de Sanctum para manejar dominios permitidos
