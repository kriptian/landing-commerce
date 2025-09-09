<?php

use App\Http\Controllers\Admin\ProductController as AdminProductController;
use App\Http\Controllers\CategoryController;
// Se elimina LandingPageController si ya no se usa para esta ruta
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\Public\ProductController as PublicProductController; // Importante que esté este
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

// ----------- AQUÍ ESTÁ EL ÚNICO CAMBIO -----------
// Apuntamos al controlador y método correctos
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

        // --- AÑADÍ ESTA NUEVA RUTA PARA CREAR SUBCATEGORÍAS ---
        Route::post('categories/{parentCategory}/subcategories', [CategoryController::class, 'storeSubcategory'])->name('categories.storeSubcategory');

        // Aquí le decimos explícitamente que use el controlador de Admin
        Route::resource('products', AdminProductController::class);
        // AÑADE ESTA LÍNEA
        Route::resource('users', \App\Http\Controllers\Admin\UserController::class);
    });
});

require __DIR__.'/auth.php';