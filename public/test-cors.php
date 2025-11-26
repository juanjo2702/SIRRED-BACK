<?php
/**
 * Script de Verificación de Configuración CORS
 * Subir este archivo a: public/test-cors.php
 * Acceder desde: https://api.sirred.clubatleticoimperial.com/test-cors.php
 */

header('Content-Type: application/json');

$response = [
    'status' => 'OK',
    'timestamp' => date('Y-m-d H:i:s'),
    'checks' => []
];

// 1. Verificar módulos de Apache
$response['checks']['apache_modules'] = [
    'mod_rewrite' => function_exists('apache_get_modules') && in_array('mod_rewrite', apache_get_modules()),
    'mod_headers' => function_exists('apache_get_modules') && in_array('mod_headers', apache_get_modules()),
];

// 2. Verificar headers CORS que se están enviando
$response['checks']['cors_headers'] = [
    'Access-Control-Allow-Origin' => isset($_SERVER['HTTP_ACCESS_CONTROL_ALLOW_ORIGIN']) ? $_SERVER['HTTP_ACCESS_CONTROL_ALLOW_ORIGIN'] : 'NOT SET',
    'Access-Control-Allow-Methods' => isset($_SERVER['HTTP_ACCESS_CONTROL_ALLOW_METHODS']) ? $_SERVER['HTTP_ACCESS_CONTROL_ALLOW_METHODS'] : 'NOT SET',
    'Access-Control-Allow-Headers' => isset($_SERVER['HTTP_ACCESS_CONTROL_ALLOW_HEADERS']) ? $_SERVER['HTTP_ACCESS_CONTROL_ALLOW_HEADERS'] : 'NOT SET',
];

// 3. Verificar variables de entorno
$response['checks']['environment'] = [
    'SANCTUM_STATEFUL_DOMAINS' => getenv('SANCTUM_STATEFUL_DOMAINS') ?: 'NOT SET',
    'APP_ENV' => getenv('APP_ENV') ?: 'NOT SET',
    'APP_DEBUG' => getenv('APP_DEBUG') ?: 'NOT SET',
];

// 4. Verificar método de la petición
$response['request'] = [
    'method' => $_SERVER['REQUEST_METHOD'],
    'origin' => isset($_SERVER['HTTP_ORIGIN']) ? $_SERVER['HTTP_ORIGIN'] : 'NOT SET',
    'headers' => getallheaders(),
];

// 5. Verificar si .htaccess está siendo procesado
$response['checks']['htaccess'] = [
    'file_exists' => file_exists(__DIR__ . '/.htaccess'),
    'readable' => is_readable(__DIR__ . '/.htaccess'),
];

// Si es una petición OPTIONS, responder inmediatamente
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    $response['message'] = 'Preflight OPTIONS request received';
    http_response_code(200);
    echo json_encode($response, JSON_PRETTY_PRINT);
    exit;
}

$response['message'] = 'Configuration check completed';
echo json_encode($response, JSON_PRETTY_PRINT);
