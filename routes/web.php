<?php

use App\Http\Controllers\Admin\ProductController as AdminProductController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\Public\ProductController as PublicProductController;
use App\Http\Controllers\Admin\RoleController; // <-- 1. AÑADIMOS EL NUEVO CONTROLADOR
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

/*
|--------------------------------------------------------------------------
| Rutas Públicas
|--------------------------------------------------------------------------
*/
Route::get('/', function () {
    return redirect()->route('login');
});

Route::get('/catalogo', [PublicProductController::class, 'index'])->name('catalogo.index');

Route::get('/producto/{product}', [PublicProductController::class, 'show'])->name('catalogo.show');

/*
|--------------------------------------------------------------------------
| Rutas Privadas (Panel de Administración)
|--------------------------------------------------------------------------
*/
Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/dashboard', function () {
        return Inertia::render('Dashboard');
    })->name('dashboard');

    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    // Agrupamos las rutas de admin bajo el prefijo /admin y con nombre admin.
    Route::prefix('admin')->name('admin.')->group(function () {
        Route::resource('categories', CategoryController::class);
        Route::post('categories/{parentCategory}/subcategories', [CategoryController::class, 'storeSubcategory'])->name('categories.storeSubcategory');

        Route::resource('products', AdminProductController::class);
        Route::resource('users', \App\Http\Controllers\Admin\UserController::class);
        
        // --- 2. AÑADIMOS LA NUEVA RUTA PARA ROLES ---
        Route::resource('roles', RoleController::class);
    });
});

require __DIR__.'/auth.php';