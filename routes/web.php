<?php

use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use App\Models\Store;
use Illuminate\Http\Request;

// Controladores Públicos
use App\Http\Controllers\Public\ProductController as PublicProductController;
use App\Http\Controllers\Public\CheckoutController;

// Controladores de Autenticación y Perfil
use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\StoreSetupController;

// ===== AQUÍ ESTÁN LOS CAMBIOS =====
// Controladores del Panel de Admin
use App\Http\Controllers\Admin\CategoryController;
use App\Http\Controllers\Admin\InventoryController;
use App\Http\Controllers\Admin\OrderController;
use App\Http\Middleware\EnsureUserIsAdmin;
use App\Http\Controllers\Admin\ProductController as AdminProductController;
use App\Http\Controllers\Admin\ReportController;
use App\Http\Controllers\Admin\RoleController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\CartController; // Este es global pero se usa en rutas auth
use Illuminate\Support\Facades\Artisan;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
*/

Route::get('/', function (Request $request) {
    // Intento de resolver tienda por dominio personalizado
    $host = $request->getHost();
    $store = \App\Models\Store::where('custom_domain', $host)->first();
    if ($store) {
        // Redirige 301 al catálogo público de esa tienda usando el slug
        return redirect()->route('catalogo.index', ['store' => $store->slug], 301);
    }
    // Si no hay dominio de tienda, mostrar la landing comercial
    return Inertia::render('Public/Landing');
});

// Rutas Públicas de la Tienda
Route::get('/tiendas', function () {
    return Inertia::render('Public/StoreIndex', [
        'stores' => Store::all(),
    ]);
})->name('home');

Route::get('/tienda/{store:slug}', [PublicProductController::class, 'index'])->name('catalogo.index');
Route::get('/tienda/{store:slug}/producto/{product}', [PublicProductController::class, 'show'])->name('catalogo.show');

// Carrito (público, basado en sesión)
Route::post('/cart', [CartController::class, 'store'])->name('cart.store');
Route::get('/tienda/{store:slug}/cart', [CartController::class, 'index'])->name('cart.index');
Route::delete('/cart/{cart}', [CartController::class, 'destroy'])->name('cart.destroy');
Route::delete('/guest-cart/{key}', [CartController::class, 'destroyGuest'])->name('cart.guest.destroy');

// Checkout público (no requiere autenticación)
Route::get('/tienda/{store:slug}/checkout', [CheckoutController::class, 'index'])->name('checkout.index');
Route::post('/tienda/{store:slug}/checkout', [CheckoutController::class, 'store'])->name('checkout.store');

// Rutas Privadas (que requieren autenticación)
Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/dashboard', function (Request $request) {
        $store = $request->user()->store;

        $todayStart = now()->startOfDay();
        $todayEnd = now()->endOfDay();

        $deliveredToday = $store->orders()
            ->whereBetween('created_at', [$todayStart, $todayEnd])
            ->where('status', 'entregado');

        $salesToday = (clone $deliveredToday)->sum('total_price');
        $ordersToday = (clone $deliveredToday)->count();
        $avgTicketToday = $ordersToday > 0 ? $salesToday / $ordersToday : 0;

        return Inertia::render('Dashboard', [
            'store' => $store,
            'metrics' => [
                'salesToday' => $salesToday,
                'ordersToday' => $ordersToday,
                'avgTicketToday' => $avgTicketToday,
            ],
        ]);
    })->name('dashboard');

    // Configuración de la tienda
    Route::get('/store/setup', [StoreSetupController::class, 'create'])->name('store.setup');
    Route::post('/store/setup', [StoreSetupController::class, 'store'])->name('store.save');

    // Perfil del Usuario
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    // (Checkout quedó público)

    // Panel de Administración (acceso según permisos por tienda)
    Route::prefix('admin')->name('admin.')->group(function () {
        // ===== ESTAS SON LAS RUTAS QUE CORREGIMOS =====
        Route::resource('categories', CategoryController::class);
        Route::post('categories/{parentCategory}/subcategories', [CategoryController::class, 'storeSubcategory'])->name('categories.storeSubcategory');
        Route::resource('products', AdminProductController::class);
        Route::put('products-store/promo', [AdminProductController::class, 'updateStorePromo'])->name('products.store_promo');
        Route::resource('users', UserController::class);
        Route::resource('roles', RoleController::class);
        Route::resource('orders', OrderController::class);
        Route::post('orders/{order}/confirm', [OrderController::class, 'confirm'])->name('orders.confirm');
        Route::get('reports', [ReportController::class, 'index'])->name('reports.index');
        Route::get('reports/export', [ReportController::class, 'export'])->name('reports.export');
        Route::get('inventory', [InventoryController::class, 'index'])->name('inventory.index');
    });

    // Rutas exclusivas de Súper Admin
    Route::prefix('super')->name('super.')->middleware(EnsureUserIsAdmin::class)->group(function () {
        Route::resource('stores', \App\Http\Controllers\SuperAdmin\StoreController::class);
    });
});

require __DIR__.'/auth.php';

Route::get('/crear-link-de-almacenamiento', function () {
    try {
        Artisan::call('storage:link');
        return '¡El link de almacenamiento se creó con éxito!';
    } catch (\Exception $e) {
        return 'Error al crear el link: ' . $e->getMessage();
    }
});