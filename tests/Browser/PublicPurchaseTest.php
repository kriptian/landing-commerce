<?php

use App\Models\Product;
use App\Models\Store;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Laravel\Dusk\Browser;

function browserPurchaseStore(string $slug): Store
{
    $owner = User::factory()->create();
    $store = Store::create([
        'name' => str($slug)->headline()->toString(),
        'slug' => $slug,
        'user_id' => $owner->id,
        'phone' => '573001234567',
        'catalog_show_buy_button' => true,
        'catalog_product_template' => 'default',
        'gallery_type' => 'custom',
    ]);
    $owner->update(['store_id' => $store->id]);

    return $store;
}

function browserPurchaseProduct(Store $store, string $name, bool $trackInventory = false): Product
{
    $categoryId = DB::table('categories')->insertGetId([
        'name' => $name.' category',
        'store_id' => $store->id,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    return $store->products()->create([
        'category_id' => $categoryId,
        'name' => $name,
        'price' => 2500,
        'quantity' => $trackInventory ? 0 : 10,
        'track_inventory' => $trackInventory,
        'is_active' => true,
    ]);
}

test('a simple product buy button reaches checkout', function () {
    $store = browserPurchaseStore('dusk-simple-store');
    $product = browserPurchaseProduct($store, 'Dusk Simple Product');
    $this->browse(function (Browser $browser) use ($store, $product) {
        $browser->visit('/'.$store->slug)
            ->waitFor('@catalog-buy-now-'.$product->id)
            ->click('@catalog-buy-now-'.$product->id)
            ->waitForLocation('/'.$store->slug.'/checkout')
            ->assertPresent('@checkout-page')
            ->assertSee($product->name);
    });
});

test('a configurable product requires a real variant before checkout', function () {
    $store = browserPurchaseStore('dusk-variant-store');
    $product = browserPurchaseProduct($store, 'Dusk Variant Product');
    $product->variants()->create([
        'options' => ['Color' => 'Azul'],
        'price' => 2800,
        'stock' => 5,
    ]);

    $this->browse(function (Browser $browser) use ($store, $product) {
        $browser->visit('/'.$store->slug)
            ->waitFor('@catalog-buy-now-'.$product->id)
            ->click('@catalog-buy-now-'.$product->id)
            ->waitForLocation('/'.$store->slug.'/producto/'.$product->id)
            ->assertButtonDisabled('@product-buy-now')
            ->click('@variant-option-0-0')
            ->waitUntilEnabled('@product-buy-now')
            ->click('@product-buy-now')
            ->waitForLocation('/'.$store->slug.'/checkout')
            ->assertPresent('@checkout-page')
            ->assertSee('Color: Azul');
    });
});

test('an out of stock variant cannot be purchased from the catalog', function () {
    $store = browserPurchaseStore('dusk-stock-store');
    $product = browserPurchaseProduct($store, 'Dusk Out Of Stock Product', true);
    $product->variants()->create([
        'options' => ['Talla' => 'M'],
        'price' => 2500,
        'stock' => 0,
    ]);

    $this->browse(function (Browser $browser) use ($store, $product) {
        $browser->visit('/'.$store->slug)
            ->waitFor('@catalog-buy-now-'.$product->id)
            ->assertButtonDisabled('@catalog-buy-now-'.$product->id)
            ->assertSee('Agotado');
    });
});
