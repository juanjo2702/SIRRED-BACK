<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\GestionController;
use App\Http\Controllers\CorteController;
use App\Http\Controllers\SedeController;
use App\Http\Controllers\CarreraController;
use App\Http\Controllers\FacturacionController;
use App\Http\Controllers\FacturaExportController;
use App\Http\Controllers\RezagadoController;
use App\Http\Controllers\PublicController;
use App\Http\Controllers\RoleController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DocenteController;

Route::post('/login', [AuthController::class, 'login']);

// Public routes (no auth required)
Route::get('/public/search', [PublicController::class, 'searchByCI']);
Route::post('/public/facturaciones/{facturacion}/upload', [FacturacionController::class, 'uploadFactura'])
    ->middleware('throttle:5,1'); // Máximo 5 intentos por minuto por IP

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::post('/change-password', [AuthController::class, 'changePassword']);
    Route::get('/user', function (Request $request) {
        return $request->user();
    });

    // Dashboard
    Route::get('dashboard/stats', [DashboardController::class, 'getStats']);

    Route::apiResource('users', UserController::class);
    Route::post('users/{user}/reset-password', [UserController::class, 'resetPassword']);
    Route::post('users/{user}/toggle-status', [UserController::class, 'toggleStatus']);

    Route::get('roles', [RoleController::class, 'index']);

    Route::apiResource('gestiones', GestionController::class);
    Route::apiResource('cortes', CorteController::class);

    Route::apiResource('sedes', SedeController::class);
    Route::post('sedes/{sede}/carreras', [SedeController::class, 'attachCarrera']);
    Route::post('sedes/{sede}/sync-carreras', [SedeController::class, 'syncCarreras']);
    Route::delete('sedes/{sede}/carreras/{carrera}', [SedeController::class, 'detachCarrera']);

    Route::apiResource('carreras', CarreraController::class);

    // Docentes
    Route::get('docentes', [DocenteController::class, 'index']);
    Route::put('docentes/{docente}', [DocenteController::class, 'update']);

    // Facturaciones - Core CRUD
    Route::post('facturaciones/upload-excel', [FacturacionController::class, 'uploadExcel']);
    Route::get('facturaciones', [FacturacionController::class, 'getFacturaciones']);
    Route::post('facturaciones/{facturacion}/upload-factura', [FacturacionController::class, 'uploadFactura']);
    Route::post('facturaciones/{facturacion}/deny', [FacturacionController::class, 'denyFactura']);
    Route::post('facturaciones/{facturacion}/approve', [FacturacionController::class, 'approveFactura']);
    Route::put('facturaciones/{facturacion}', [FacturacionController::class, 'update']);
    Route::post('facturaciones/bulk-update', [FacturacionController::class, 'bulkUpdate']);
    Route::get('facturaciones/datos-extraidos', [FacturacionController::class, 'getDatosExtraidos']);

    // Facturaciones - Exports (FacturaExportController)
    Route::get('facturaciones/export', [FacturaExportController::class, 'exportFacturaciones']);
    Route::get('facturaciones/export-datos-extraidos', [FacturaExportController::class, 'exportDatosExtraidos']);
    Route::get('facturaciones/export-rezagados', [FacturaExportController::class, 'exportRezagados']);
    Route::get('facturaciones/export-datos-rezagados', [FacturaExportController::class, 'exportDatosRezagados']);

    // Rezagados (RezagadoController)
    Route::get('facturaciones/rezagados', [RezagadoController::class, 'index']);
});
