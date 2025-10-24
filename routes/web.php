<?php

use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use App\Models\Store;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

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
    // Resolver tienda por dominio personalizado con normalización:
    // - minúsculas
    // - soportar con o sin "www."
    $host = Str::lower($request->getHost());
    $hostStripped = preg_replace('/^www\./', '', $host);

    $store = \App\Models\Store::query()
        ->whereIn('custom_domain', [
            $host,
            $hostStripped,
            'www.' . $hostStripped,
        ])
        ->first();

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

// (Rutas públicas sin "/tienda") se definen más abajo para no interferir con rutas /admin

// Rutas Privadas (que requieren autenticación)
Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/dashboard', function (Request $request) {
        $user = $request->user();
        $store = $user?->store; // Puede ser null

        $metrics = [
            'salesToday' => 0,
            'ordersToday' => 0,
            'avgTicketToday' => 0,
        ];

        if ($store) {
            $todayStart = now()->startOfDay();
            $todayEnd = now()->endOfDay();

            $deliveredToday = $store->orders()
                ->whereBetween('created_at', [$todayStart, $todayEnd])
                ->where('status', 'entregado');

            $salesToday = (clone $deliveredToday)->sum('total_price');
            $ordersToday = (clone $deliveredToday)->count();
            $avgTicketToday = $ordersToday > 0 ? $salesToday / $ordersToday : 0;

            $metrics = [
                'salesToday' => $salesToday,
                'ordersToday' => $ordersToday,
                'avgTicketToday' => $avgTicketToday,
            ];
        }

        return Inertia::render('Dashboard', [
            'store' => $store,
            'metrics' => $metrics,
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
        // ===== RUTAS BASE (todos los planes) =====
        Route::resource('categories', CategoryController::class);
        Route::post('categories/{parentCategory}/subcategories', [CategoryController::class, 'storeSubcategory'])->name('categories.storeSubcategory');
        Route::get('categories/{category}/children', [CategoryController::class, 'children'])->name('categories.children');
        Route::resource('products', AdminProductController::class);
        Route::put('products-store/promo', [AdminProductController::class, 'updateStorePromo'])->name('products.store_promo');

        // ===== RUTAS AVANZADAS (solo plan negociantes) =====
        Route::middleware('plan:negociante')->group(function () {
            Route::resource('users', UserController::class);
            Route::resource('roles', RoleController::class);
            Route::resource('orders', OrderController::class);
            Route::post('orders/{order}/confirm', [OrderController::class, 'confirm'])->name('orders.confirm');
            Route::get('reports', [ReportController::class, 'index'])->name('reports.index');
            Route::get('reports/export', [ReportController::class, 'export'])->name('reports.export');
            Route::get('inventory', [InventoryController::class, 'index'])->name('inventory.index');
            Route::get('inventory/export', [InventoryController::class, 'export'])->name('inventory.export');
        });
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

// ========================
// RUTAS PÚBLICAS EN RAÍZ (sin "/tienda")
// Defínelas al final para no capturar rutas /admin o del sistema
// ========================

// Slugs reservados para no colisionar con rutas del sistema cuando usamos la raíz
$reservedSlugs = implode('|', [
    'admin','login','register','logout','profile','dashboard','tiendas','cart','checkout','api','storage','crear-link-de-almacenamiento'
]);
Route::pattern('store', "^(?!($reservedSlugs)$)[A-Za-z0-9\-_.]+$");

Route::get('/{store:slug}', [PublicProductController::class, 'index'])->name('catalogo.index');
Route::get('/{store:slug}/categories/{category}/children', [PublicProductController::class, 'children'])->name('catalog.categories.children');
Route::get('/{store:slug}/producto/{product}', [PublicProductController::class, 'show'])->name('catalogo.show');

// Carrito (público, basado en sesión)
Route::post('/cart', [CartController::class, 'store'])->name('cart.store');
Route::get('/{store:slug}/cart', [CartController::class, 'index'])->name('cart.index');
Route::delete('/cart/{cart}', [CartController::class, 'destroy'])->name('cart.destroy');
Route::delete('/guest-cart/{key}', [CartController::class, 'destroyGuest'])->name('cart.guest.destroy');

// Checkout público (no requiere autenticación)
Route::get('/{store:slug}/checkout', [CheckoutController::class, 'index'])->name('checkout.index');
Route::post('/{store:slug}/checkout', [CheckoutController::class, 'store'])->name('checkout.store');

// REDIRECCIONES legacy desde "/tienda/..." a las nuevas URLs en raíz (301)
Route::redirect('/tienda/{store}', '/{store}', 301);
Route::redirect('/tienda/{store}/producto/{product}', '/{store}/producto/{product}', 301);
Route::redirect('/tienda/{store}/categories/{category}/children', '/{store}/categories/{category}/children', 301);
Route::redirect('/tienda/{store}/checkout', '/{store}/checkout', 301);
Route::redirect('/tienda/{store}/cart', '/{store}/cart', 301);