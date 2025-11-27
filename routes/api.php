<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\CorteController;
use App\Http\Controllers\SedeController;
use App\Http\Controllers\CarreraController;
use App\Http\Controllers\FacturacionController;
use App\Http\Controllers\PublicController;
use App\Http\Controllers\RoleController;
use App\Http\Controllers\DashboardController;

Route::post('/login', [AuthController::class, 'login']);

// Public routes (no auth required)
Route::get('/public/search', [PublicController::class, 'searchByCI']);
Route::post('/public/facturaciones/{facturacion}/upload', [FacturacionController::class, 'uploadFactura']);

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

    Route::apiResource('cortes', CorteController::class);

    Route::apiResource('sedes', SedeController::class);
    Route::post('sedes/{sede}/carreras', [SedeController::class, 'attachCarrera']);
    Route::post('sedes/{sede}/sync-carreras', [SedeController::class, 'syncCarreras']);
    Route::delete('sedes/{sede}/carreras/{carrera}', [SedeController::class, 'detachCarrera']);

    Route::apiResource('carreras', CarreraController::class);

    Route::post('facturaciones/upload-excel', [FacturacionController::class, 'uploadExcel']);
    Route::get('facturaciones', [FacturacionController::class, 'getFacturaciones']);
    Route::post('facturaciones/{facturacion}/upload-factura', [FacturacionController::class, 'uploadFactura']);
    Route::post('facturaciones/{facturacion}/deny', [FacturacionController::class, 'denyFactura']);
    Route::post('facturaciones/{facturacion}/approve', [FacturacionController::class, 'approveFactura']);
    Route::get('facturaciones/export', [FacturacionController::class, 'exportFacturaciones']);
});
