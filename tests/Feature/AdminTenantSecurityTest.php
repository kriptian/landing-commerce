<?php

use App\Models\Category;
use App\Models\Product;
use App\Models\Store;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;

function createTenant(string $name): array
{
    $user = User::factory()->create();
    $store = Store::create([
        'name' => $name,
        'user_id' => $user->id,
        'plan' => 'emprendedor',
    ]);
    $user->update(['store_id' => $store->id]);

    return [$user->fresh(), $store];
}

function createTenantProduct(Store $store, string $name, float $price): Product
{
    $category = Category::create([
        'name' => $name.' category',
        'store_id' => $store->id,
    ]);

    return $store->products()->create([
        'name' => $name,
        'price' => $price,
        'category_id' => $category->id,
        'quantity' => 10,
        'track_inventory' => true,
        'is_active' => true,
    ]);
}

test('api product mutations cannot cross tenant boundaries', function () {
    [$firstUser] = createTenant('First tenant');
    [, $secondStore] = createTenant('Second tenant');
    $foreignProduct = createTenantProduct($secondStore, 'Foreign product', 100);
    $firstUser->givePermissionTo([
        Permission::findOrCreate('ver inventario'),
        Permission::findOrCreate('editar productos'),
        Permission::findOrCreate('eliminar productos'),
    ]);

    Sanctum::actingAs($firstUser);

    $this->getJson("/api/products/{$foreignProduct->id}")->assertNotFound();
    $this->putJson("/api/products/{$foreignProduct->id}", ['price' => 1])->assertNotFound();
    $this->deleteJson("/api/products/{$foreignProduct->id}")->assertNotFound();

    expect($foreignProduct->fresh()->price)->toEqual(100);
});

test('physical sale rejects a product from another tenant', function () {
    [$user, $store] = createTenant('POS tenant');
    [, $otherStore] = createTenant('Other POS tenant');
    $foreignProduct = createTenantProduct($otherStore, 'Foreign POS product', 100);
    $user->assignRole($store->roles()->where('name', 'physical-sales')->firstOrFail());

    $this->actingAs($user)->postJson(route('admin.physical-sales.store'), [
        'items' => [[
            'product_id' => $foreignProduct->id,
            'quantity' => 1,
            'unit_price' => 1,
        ]],
        'subtotal' => 1,
        'tax' => 0,
        'total' => 1,
        'payment_method' => 'efectivo',
        'amount_tendered' => 100,
        'idempotency_key' => (string) Str::uuid(),
    ])->assertUnprocessable();

    expect($store->physicalSales()->count())->toBe(0);
});

test('physical sale recalculates client supplied prices and totals', function () {
    [$user, $store] = createTenant('Secure POS tenant');
    $product = createTenantProduct($store, 'Authoritative product', 125);
    $user->assignRole($store->roles()->where('name', 'physical-sales')->firstOrFail());

    $this->actingAs($user)->postJson(route('admin.physical-sales.store'), [
        'items' => [[
            'product_id' => $product->id,
            'quantity' => 2,
            'unit_price' => 1,
            'discount_percent' => 99,
        ]],
        'subtotal' => 2,
        'tax' => 99,
        'discount' => 99,
        'total' => 1,
        'payment_method' => 'efectivo',
        'amount_tendered' => 250,
        'idempotency_key' => (string) Str::uuid(),
    ])->assertOk();

    $sale = $store->physicalSales()->with('items')->firstOrFail();
    expect((float) $sale->subtotal)->toBe(250.0)
        ->and((float) $sale->tax)->toBe(0.0)
        ->and((float) $sale->total)->toBe(250.0)
        ->and((float) $sale->items->first()->unit_price)->toBe(125.0)
        ->and((float) $sale->items->first()->subtotal)->toBe(250.0);
});

test('unverified physical seller can use pos but cannot enter the admin panel', function () {
    [$user, $store] = createTenant('Unverified POS tenant');
    $user->forceFill(['email_verified_at' => null])->save();
    $user->assignRole($store->roles()->where('name', 'physical-sales')->firstOrFail());

    $this->actingAs($user)
        ->get(route('admin.physical-sales.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('auth.capabilities.physicalSales', 'enabled')
            ->where('auth.capabilities.dashboard', 'hidden')
            ->where('auth.capabilities.reports', 'hidden'));

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertRedirect(route('admin.physical-sales.index'));

    $this->actingAs($user)
        ->get(route('admin.physical-sales.export'))
        ->assertRedirect(route('admin.physical-sales.index'));
});

test('unverified regular user cannot bypass email verification through pos', function () {
    [$user] = createTenant('Unverified admin tenant');
    $user->forceFill(['email_verified_at' => null])->save();
    $user->givePermissionTo(Permission::findOrCreate('ver inventario'));

    $this->actingAs($user)
        ->get(route('admin.physical-sales.index'))
        ->assertRedirect(route('verification.notice'));
});

test('admin navigation capabilities match permissions and plan', function () {
    [$user, $store] = createTenant('Navigation tenant');
    $user->givePermissionTo(collect([
        'ver dashboard',
        'ver inventario',
        'ver ordenes',
        'ver clientes',
        'gestionar cupones',
        'gestionar categorias',
        'gestionar galeria',
        'ver reportes',
        'gestionar usuarios',
    ])->map(fn (string $name) => Permission::findOrCreate($name)));

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('auth.capabilities.dashboard', 'enabled')
            ->where('auth.capabilities.products', 'enabled')
            ->where('auth.capabilities.orders', 'locked')
            ->where('auth.capabilities.customers', 'locked')
            ->where('auth.capabilities.superStores', 'hidden'));

    $store->update(['plan' => 'negociante']);

    $this->actingAs($user->fresh())
        ->get(route('dashboard'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('auth.capabilities.orders', 'enabled')
            ->where('auth.capabilities.customers', 'enabled')
            ->where('auth.capabilities.inventory', 'enabled')
            ->where('auth.capabilities.users', 'enabled'));
});

test('authorized pos price and general discount overrides are persisted authoritatively', function () {
    [$user, $store] = createTenant('POS override tenant');
    $product = createTenantProduct($store, 'Discounted product', 125);
    $user->assignRole($store->roles()->where('name', 'physical-sales')->firstOrFail());
    $user->givePermissionTo(Permission::findOrCreate('modificar precios y descuentos pos'));

    $this->actingAs($user)->postJson(route('admin.physical-sales.store'), [
        'items' => [[
            'product_id' => $product->id,
            'quantity' => 2,
            'unit_price' => 100,
        ]],
        'discount' => 25,
        'payment_method' => 'efectivo',
        'amount_tendered' => 200,
        'idempotency_key' => (string) Str::uuid(),
    ])->assertOk();

    $sale = $store->physicalSales()->with('items')->firstOrFail();
    expect((float) $sale->subtotal)->toBe(200.0)
        ->and((float) $sale->discount)->toBe(25.0)
        ->and((float) $sale->total)->toBe(175.0)
        ->and((float) $sale->amount_tendered)->toBe(200.0)
        ->and((float) $sale->change_due)->toBe(25.0)
        ->and((float) $sale->items->first()->original_price)->toBe(125.0)
        ->and((float) $sale->items->first()->unit_price)->toBe(100.0)
        ->and((float) $sale->items->first()->discount_percent)->toBe(20.0);
});

test('pos rejects insufficient cash without creating a sale or decrementing stock', function () {
    [$user, $store] = createTenant('POS cash tenant');
    $product = createTenantProduct($store, 'Cash product', 125);
    $user->assignRole($store->roles()->where('name', 'physical-sales')->firstOrFail());

    $this->actingAs($user)->postJson(route('admin.physical-sales.store'), [
        'items' => [[
            'product_id' => $product->id,
            'quantity' => 1,
        ]],
        'payment_method' => 'efectivo',
        'amount_tendered' => 100,
        'idempotency_key' => (string) Str::uuid(),
    ])->assertUnprocessable()->assertJsonValidationErrors('amount_tendered');

    expect($store->physicalSales()->count())->toBe(0)
        ->and($product->fresh()->quantity)->toBe(10);
});

test('pos idempotency key prevents duplicate sales and stock decrements', function () {
    [$user, $store] = createTenant('POS idempotency tenant');
    $product = createTenantProduct($store, 'Idempotent product', 50);
    $user->assignRole($store->roles()->where('name', 'physical-sales')->firstOrFail());
    $idempotencyKey = (string) Str::uuid();
    $payload = [
        'items' => [[
            'product_id' => $product->id,
            'quantity' => 1,
        ]],
        'payment_method' => 'efectivo',
        'amount_tendered' => 50,
        'idempotency_key' => $idempotencyKey,
    ];

    $this->actingAs($user)->postJson(route('admin.physical-sales.store'), $payload)->assertOk();
    $this->actingAs($user)->postJson(route('admin.physical-sales.store'), $payload)->assertOk();

    expect($store->physicalSales()->count())->toBe(1)
        ->and($product->fresh()->quantity)->toBe(9);
});

test('pos rejects unsupported mixed payments', function () {
    [$user, $store] = createTenant('POS payment tenant');
    $product = createTenantProduct($store, 'Payment product', 50);
    $user->assignRole($store->roles()->where('name', 'physical-sales')->firstOrFail());

    $this->actingAs($user)->postJson(route('admin.physical-sales.store'), [
        'items' => [[
            'product_id' => $product->id,
            'quantity' => 1,
        ]],
        'payment_method' => 'mixto',
        'idempotency_key' => (string) Str::uuid(),
    ])->assertUnprocessable()->assertJsonValidationErrors('payment_method');

    expect($store->physicalSales()->count())->toBe(0);
});

test('automatic pos promotions remain item snapshots instead of sale discounts', function () {
    [$user, $store] = createTenant('POS promotion tenant');
    $product = createTenantProduct($store, 'Promotional product', 100);
    $product->update([
        'promo_active' => true,
        'promo_discount_percent' => 10,
    ]);
    $user->assignRole($store->roles()->where('name', 'physical-sales')->firstOrFail());

    $this->actingAs($user)->postJson(route('admin.physical-sales.store'), [
        'items' => [[
            'product_id' => $product->id,
            'quantity' => 1,
        ]],
        'payment_method' => 'efectivo',
        'amount_tendered' => 100,
        'idempotency_key' => (string) Str::uuid(),
    ])->assertOk();

    $sale = $store->physicalSales()->with('items')->firstOrFail();
    expect((float) $sale->subtotal)->toBe(90.0)
        ->and((float) $sale->discount)->toBe(0.0)
        ->and((float) $sale->total)->toBe(90.0)
        ->and((float) $sale->items->first()->original_price)->toBe(100.0)
        ->and((float) $sale->items->first()->unit_price)->toBe(90.0)
        ->and((float) $sale->items->first()->discount_percent)->toBe(10.0);
});

test('deleting a seller preserves physical sale history', function () {
    [, $store] = createTenant('POS history tenant');
    $seller = User::factory()->create(['store_id' => $store->id]);
    $product = createTenantProduct($store, 'History product', 50);
    $seller->assignRole($store->roles()->where('name', 'physical-sales')->firstOrFail());

    $this->actingAs($seller)->postJson(route('admin.physical-sales.store'), [
        'items' => [[
            'product_id' => $product->id,
            'quantity' => 1,
        ]],
        'payment_method' => 'efectivo',
        'amount_tendered' => 50,
        'idempotency_key' => (string) Str::uuid(),
    ])->assertOk();

    $sale = $store->physicalSales()->firstOrFail();
    $seller->delete();

    expect($sale->fresh())->not->toBeNull()
        ->and($sale->fresh()->user_id)->toBeNull();
});

test('a role manager cannot grant permissions they do not hold', function () {
    [$user, $store] = createTenant('Limited role tenant');
    $store->update(['plan' => 'negociante']);
    $manageUsers = Permission::findOrCreate('gestionar usuarios');
    Permission::findOrCreate('editar productos');
    $user->givePermissionTo($manageUsers);

    $this->actingAs($user)->post(route('admin.roles.store'), [
        'name' => 'Escalated role',
        'permissions' => ['gestionar usuarios', 'editar productos'],
    ])->assertSessionHasErrors('permissions.1');

    expect($store->roles()->where('name', 'Escalated role')->exists())->toBeFalse();
});

test('product variant option uploads reject non images', function () {
    [$user, $store] = createTenant('Upload tenant');
    $user->givePermissionTo(Permission::findOrCreate('crear productos'));
    $category = Category::create([
        'name' => 'Upload category',
        'store_id' => $store->id,
    ]);

    $this->actingAs($user)->post(route('admin.products.store'), [
        'name' => 'Upload product',
        'price' => 100,
        'category_id' => $category->id,
        'track_inventory' => false,
        'variant_options' => [[
            'name' => 'Color',
            'children' => [['name' => 'Blue']],
        ]],
        'variant_option_0_0' => UploadedFile::fake()->create('payload.php', 2, 'application/x-php'),
    ])->assertSessionHasErrors('variant_option_0_0');

    expect($store->products()->where('name', 'Upload product')->exists())->toBeFalse();
});

test('order inventory transitions apply exactly once and can be reversed', function () {
    [$user, $store] = createTenant('Order inventory tenant');
    $store->update(['plan' => 'negociante']);
    $user->givePermissionTo(Permission::findOrCreate('gestionar ordenes'));
    $product = createTenantProduct($store, 'Order product', 100);
    $order = $store->orders()->create([
        'sequence_number' => 1,
        'customer_name' => 'Customer',
        'customer_phone' => '3001234567',
        'customer_email' => 'customer@example.test',
        'customer_address' => 'Main Street',
        'total_price' => 200,
        'status' => 'recibido',
    ]);
    $order->items()->create([
        'product_id' => $product->id,
        'quantity' => 2,
        'unit_price' => 100,
        'product_name' => $product->name,
    ]);

    $this->actingAs($user)->put(route('admin.orders.update', $order), ['status' => 'despachado']);
    expect($product->fresh()->quantity)->toBe(8);

    $this->actingAs($user)->put(route('admin.orders.update', $order), ['status' => 'despachado']);
    $this->actingAs($user)->put(route('admin.orders.update', $order), ['status' => 'entregado']);
    expect($product->fresh()->quantity)->toBe(8);

    $this->actingAs($user)->put(route('admin.orders.update', $order), ['status' => 'cancelado']);
    expect($product->fresh()->quantity)->toBe(10);
});

test('coupons reject products from another tenant', function () {
    [$user, $store] = createTenant('Coupon tenant');
    $store->update(['plan' => 'negociante']);
    [, $otherStore] = createTenant('Foreign coupon tenant');
    $foreignProduct = createTenantProduct($otherStore, 'Foreign coupon product', 100);
    $user->givePermissionTo(Permission::findOrCreate('gestionar cupones'));

    $this->actingAs($user)->post(route('admin.coupons.store'), [
        'code' => 'SECURE10',
        'type' => 'percentage',
        'value' => 10,
        'product_ids' => [$foreignProduct->id],
    ])->assertSessionHasErrors('product_ids.0');

    expect($store->coupons()->where('code', 'SECURE10')->exists())->toBeFalse();
});
