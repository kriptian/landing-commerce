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
use App\Http\Controllers\CartController; 
use App\Http\Controllers\Public\CheckoutController; 

/*
|--------------------------------------------------------------------------
| Rutas Públicas
|--------------------------------------------------------------------------
*/

Route::get('/', function () {
    return redirect()->route('login');
});

// --- AJUSTE #2: Creamos una nueva ruta para la vitrina de tiendas ---
Route::get('/tiendas', function () {
    return Inertia::render('Public/StoreIndex', [
        'stores' => Store::all(),
    ]);
})->name('home'); // El nombre 'home' ahora es de la vitrina

// Route::get('/', function () {
//     return Inertia::render('Public/StoreIndex', [
//         'stores' => Store::all(),
//     ]);
// })->name('home');

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

    // ==== AQUÍ VA LA NUEVA RUTA DEL CARRITO ====
    Route::post('/cart', [CartController::class, 'store'])->name('cart.store');
    Route::get('/tienda/{store:slug}/cart', [CartController::class, 'index'])->name('cart.index');
    Route::delete('/cart/{cart}', [CartController::class, 'destroy'])->name('cart.destroy');


    // ============================================

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
        Route::resource('orders', \App\Http\Controllers\Admin\OrderController::class);
        Route::post('orders/{order}/confirm', [\App\Http\Controllers\Admin\OrderController::class, 'confirm'])->name('orders.confirm');
    });
    Route::get('/tienda/{store:slug}/checkout', [CheckoutController::class, 'index'])->name('checkout.index');
    Route::post('/tienda/{store:slug}/checkout', [CheckoutController::class, 'store'])->name('checkout.store');
});

require __DIR__.'/auth.php';