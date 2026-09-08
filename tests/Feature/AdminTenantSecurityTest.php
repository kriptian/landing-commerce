<?php

use App\Models\Category;
use App\Models\Product;
use App\Models\Store;
use App\Models\User;
use Illuminate\Http\UploadedFile;
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
    ])->assertOk();

    $sale = $store->physicalSales()->with('items')->firstOrFail();
    expect((float) $sale->subtotal)->toBe(250.0)
        ->and((float) $sale->tax)->toBe(0.0)
        ->and((float) $sale->total)->toBe(250.0)
        ->and((float) $sale->items->first()->unit_price)->toBe(125.0)
        ->and((float) $sale->items->first()->subtotal)->toBe(250.0);
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
