<?php

use App\Models\Category;
use App\Models\Product;
use App\Models\Store;
use App\Models\User;
use Spatie\Permission\Models\Permission;

function inventoryTenant(string $name): array
{
    $user = User::factory()->create();
    $store = Store::create([
        'name' => $name,
        'user_id' => $user->id,
        'plan' => 'negociante',
    ]);
    $user->update(['store_id' => $store->id]);
    $user->givePermissionTo([
        Permission::findOrCreate('ver inventario'),
        Permission::findOrCreate('editar inventario'),
    ]);

    return [$user->fresh(), $store];
}

function inventoryProduct(Store $store, string $name): Product
{
    $category = Category::create([
        'name' => $name.' category',
        'store_id' => $store->id,
    ]);

    return $store->products()->create([
        'name' => $name,
        'barcode' => 'BAR-'.$name,
        'price' => 100,
        'purchase_price' => 60,
        'category_id' => $category->id,
        'quantity' => 5,
        'track_inventory' => true,
        'is_active' => true,
    ]);
}

test('inventory search finds sku and options with a normalized thumbnail inside the tenant', function () {
    [$user, $store] = inventoryTenant('Inventory search tenant');
    [, $otherStore] = inventoryTenant('Other inventory tenant');
    $product = inventoryProduct($store, 'Own shirt');
    $product->images()->create(['path' => 'products/own-shirt.jpg']);
    $product->variants()->create([
        'options' => ['Color' => 'Azul océano', 'Talla' => 'M'],
        'sku' => 'SKU-OCEAN-M',
        'stock' => 3,
    ]);
    inventoryProduct($otherStore, 'Foreign SKU-OCEAN-M');

    $this->actingAs($user)
        ->getJson(route('admin.inventory.search', ['q' => 'OCEAN']))
        ->assertOk()
        ->assertJsonCount(1)
        ->assertJsonPath('0.id', $product->id)
        ->assertJsonPath('0.thumbnail', '/storage/products/own-shirt.jpg')
        ->assertJsonPath('0.variants.0.sku', 'SKU-OCEAN-M');

    $this->actingAs($user)
        ->getJson(route('admin.inventory.search', ['q' => 'océano']))
        ->assertOk()
        ->assertJsonCount(1)
        ->assertJsonPath('0.id', $product->id);
});

test('inventory entry updates only the selected tenant variant', function () {
    [$user, $store] = inventoryTenant('Inventory update tenant');
    $product = inventoryProduct($store, 'Variant product');
    $selected = $product->variants()->create([
        'options' => ['Color' => 'Blue'],
        'sku' => 'BLUE',
        'stock' => 2,
    ]);
    $untouched = $product->variants()->create([
        'options' => ['Color' => 'Red'],
        'sku' => 'RED',
        'stock' => 7,
    ]);

    $this->actingAs($user)->post(route('admin.inventory.quick-update'), [
        'id' => $selected->id,
        'type' => 'variant',
        'quantity_add' => 4,
        'purchase_price' => 75,
    ])->assertRedirect();

    expect($selected->fresh()->stock)->toBe(6)
        ->and((float) $selected->fresh()->purchase_price)->toBe(75.0)
        ->and($untouched->fresh()->stock)->toBe(7);
});

test('inventory entry rejects empty changes and records from another tenant', function () {
    [$user, $store] = inventoryTenant('Protected inventory tenant');
    [, $otherStore] = inventoryTenant('Foreign protected inventory tenant');
    $ownProduct = inventoryProduct($store, 'Own protected product');
    $foreignProduct = inventoryProduct($otherStore, 'Foreign protected product');

    $this->actingAs($user)->post(route('admin.inventory.quick-update'), [
        'id' => $ownProduct->id,
        'type' => 'product',
    ])->assertSessionHasErrors('quantity_add');

    $this->actingAs($user)->post(route('admin.inventory.quick-update'), [
        'id' => $foreignProduct->id,
        'type' => 'product',
        'quantity_add' => 3,
    ])->assertNotFound();

    expect($ownProduct->fresh()->quantity)->toBe(5)
        ->and($foreignProduct->fresh()->quantity)->toBe(5);
});
