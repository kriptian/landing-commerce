<?php

use App\Http\Controllers\Api\ProductController;
use App\Http\Controllers\Api\UserController;
use App\Http\Middleware\EnsureUserIsAdmin;
use Illuminate\Support\Facades\Route;

// Todas las rutas aquí dentro requieren que el usuario esté logueado
Route::middleware('auth:sanctum')->group(function () {

    // Rutas para gestionar productos
    Route::apiResource('products', ProductController::class);

    // Rutas para gestionar usuarios (protegidas para que solo los admins entren)
    Route::apiResource('users', UserController::class)->middleware(EnsureUserIsAdmin::class);

});