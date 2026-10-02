<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CorsMiddleware
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $origin = $request->headers->get('Origin') ?? '';

        $defaultAllowed = [
            'https://facturas.claure.pro',
            'http://facturas.claure.pro',
            'https://api.facturas.claure.pro',
            'http://api.facturas.claure.pro',
            'https://sirred.xpertiaplus.com',
            'https://sirred.clubatleticoimperial.com',
            'http://localhost:9000',
            'http://127.0.0.1:9000',
            'http://localhost:8000',
            'http://localhost:5173'
        ];
        $envAllowed = env('CORS_ALLOWED_ORIGINS') ? explode(',', env('CORS_ALLOWED_ORIGINS')) : [];
        $allowedOrigins = array_unique(array_merge($defaultAllowed, $envAllowed));

        $allowOrigin = 'https://facturas.claure.pro';
        if (in_array($origin, $allowedOrigins)) {
            $allowOrigin = $origin;
        } elseif ($origin && (str_contains($origin, 'claure.pro') || str_contains($origin, 'xpertiaplus.com') || str_contains($origin, 'localhost') || str_contains($origin, '127.0.0.1'))) {
            $allowOrigin = $origin;
        }

        // Handle preflight OPTIONS request
        if ($request->getMethod() === "OPTIONS") {
            return response('', 200)
                ->header('Access-Control-Allow-Origin', $allowOrigin)
                ->header('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, PATCH, OPTIONS')
                ->header('Access-Control-Allow-Headers', 'Content-Type, Authorization, X-Requested-With, Accept, Origin, X-XSRF-TOKEN')
                ->header('Access-Control-Allow-Credentials', 'true')
                ->header('Access-Control-Max-Age', '86400');
        }

        $response = $next($request);

        // Add CORS headers to all responses
        $response->headers->set('Access-Control-Allow-Origin', $allowOrigin);
        $response->headers->set('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, PATCH, OPTIONS');
        $response->headers->set('Access-Control-Allow-Headers', 'Content-Type, Authorization, X-Requested-With, Accept, Origin, X-XSRF-TOKEN');
        $response->headers->set('Access-Control-Allow-Credentials', 'true');

        return $response;
    }
}
