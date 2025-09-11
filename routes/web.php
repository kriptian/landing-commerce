<?php

use App\Http\Controllers\Admin\ProductController as AdminProductController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\Public\ProductController as PublicProductController;
use App\Http\Controllers\Admin\RoleController;
use App\Http\Controllers\StoreSetupController;
use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Models\Store;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

/*
|--------------------------------------------------------------------------
| Rutas Públicas
|--------------------------------------------------------------------------
*/
Route::get('/', function () {
    return Inertia::render('Public/StoreIndex', [
        'stores' => Store::all(),
    ]);
})->name('home');

// --- AQUÍ ESTÁN LOS AJUSTES ---
Route::get('/tienda/{store:slug}', [PublicProductController::class, 'index'])->name('catalogo.index');
Route::get('/tienda/{store:slug}/producto/{product}', [PublicProductController::class, 'show'])->name('catalogo.show');


/*
|--------------------------------------------------------------------------
| Rutas Privadas (Panel de Administración)
|--------------------------------------------------------------------------
*/
Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/dashboard', function (Request $request) {
        return Inertia::render('Dashboard', [
            'store' => $request->user()->store,
        ]);
    })->name('dashboard');

    // Rutas para configurar la tienda después del registro
    Route::get('/store/setup', [StoreSetupController::class, 'create'])->name('store.setup');
    Route::post('/store/setup', [StoreSetupController::class, 'store'])->name('store.save');

    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    // Agrupamos las rutas de admin bajo el prefijo /admin y con nombre admin.
    Route::prefix('admin')->name('admin.')->group(function () {
        Route::resource('categories', CategoryController::class);
        Route::post('categories/{parentCategory}/subcategories', [CategoryController::class, 'storeSubcategory'])->name('categories.storeSubcategory');
        Route::resource('products', AdminProductController::class);
        Route::resource('users', \App\Http\Controllers\Admin\UserController::class);
        Route::resource('roles', RoleController::class);
    });
});

require __DIR__.'/auth.php';