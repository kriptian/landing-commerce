<?php

use App\Http\Controllers\Public\CheckoutController;
use App\Models\Coupon;
use App\Models\Customer;
use App\Models\Product;
use App\Models\Store;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

function cartCheckoutSecurityStore(User $owner, string $name, string $phone = '573001234567'): Store
{
    $id = DB::table('stores')->insertGetId([
        'name' => $name,
        'slug' => (string) str($name)->slug(),
        'user_id' => $owner->id,
        'phone' => $phone,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    return Store::findOrFail($id);
}

function cartCheckoutSecurityProduct(Store $store, string $name, int $price = 1000): Product
{
    $categoryId = DB::table('categories')->insertGetId([
        'name' => $name.' category',
        'store_id' => $store->id,
        'created_at' => now(),
        'updated_at' => now(),
    ]);
    $id = DB::table('products')->insertGetId([
        'name' => $name,
        'price' => $price,
        'store_id' => $store->id,
        'category_id' => $categoryId,
        'track_inventory' => false,
        'is_active' => true,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    return Product::findOrFail($id);
}

test('cart rejects a variant belonging to another product', function () {
    $owner = User::factory()->create();
    $store = cartCheckoutSecurityStore($owner, 'Security Store');
    $product = cartCheckoutSecurityProduct($store, 'Simple');
    $otherProduct = cartCheckoutSecurityProduct($store, 'Other');
    $foreignVariant = $otherProduct->variants()->create([
        'options' => ['Color' => 'Blue'],
        'price' => 900,
        'stock' => 5,
    ]);

    $this->from('/')->post(route('cart.store'), [
        'product_id' => $product->id,
        'product_variant_id' => $foreignVariant->id,
        'quantity' => 1,
    ])->assertRedirect('/')->assertSessionHasErrors('product_variant_id');

    expect(session('guest_cart', []))->toBeEmpty();
});

test('cart requires a real combination but keeps simple products working', function () {
    $owner = User::factory()->create();
    $store = cartCheckoutSecurityStore($owner, 'Variants Store');
    $productWithVariant = cartCheckoutSecurityProduct($store, 'Shirt');
    $productWithVariant->variants()->create([
        'options' => ['Size' => 'M'],
        'price' => 1200,
        'stock' => 5,
    ]);
    $simpleProduct = cartCheckoutSecurityProduct($store, 'Cap');

    $this->from('/')->post(route('cart.store'), [
        'product_id' => $productWithVariant->id,
        'product_variant_id' => null,
        'quantity' => 1,
    ])->assertSessionHasErrors('product_variant_id');

    $this->post(route('cart.store'), [
        'product_id' => $productWithVariant->id,
        'product_variant_id' => $productWithVariant->variants()->first()->id,
        'quantity' => 1,
    ])->assertSessionHasNoErrors();

    $this->post(route('cart.store'), [
        'product_id' => $simpleProduct->id,
        'product_variant_id' => null,
        'quantity' => 1,
    ])->assertSessionHasNoErrors();

    expect(session('guest_cart'))
        ->toHaveKey('p'.$productWithVariant->id.'-v'.$productWithVariant->variants()->first()->id)
        ->toHaveKey('p'.$simpleProduct->id.'-v0');
});

test('empty synthetic variants do not block simple product purchases', function () {
    $owner = User::factory()->create();
    $store = cartCheckoutSecurityStore($owner, 'Synthetic Variant Store');
    $product = cartCheckoutSecurityProduct($store, 'Simple Product', 1800);
    $product->variants()->create([
        'options' => [],
        'price' => null,
        'stock' => 0,
    ]);

    $this->post(route('cart.store'), [
        'product_id' => $product->id,
        'product_variant_id' => null,
        'quantity' => 1,
    ])->assertSessionHasNoErrors();

    $this->post(route('checkout.store', $store), [
        'customer_name' => 'Simple Customer',
        'customer_phone' => '3001234567',
        'customer_email' => 'simple@example.com',
        'customer_address' => 'Main Street 1',
        'idempotency_key' => (string) Str::uuid(),
    ])->assertRedirect();

    $this->assertDatabaseHas('orders', [
        'store_id' => $store->id,
        'total_price' => 1800,
    ]);
    $this->assertDatabaseHas('order_items', [
        'product_id' => $product->id,
        'product_variant_id' => null,
        'unit_price' => 1800,
    ]);
});

test('cart rejects inactive products without changing the guest cart', function () {
    $owner = User::factory()->create();
    $store = cartCheckoutSecurityStore($owner, 'Inactive Product Store');
    $product = cartCheckoutSecurityProduct($store, 'Inactive Product');
    $product->update(['is_active' => false]);

    $this->post(route('cart.store'), [
        'product_id' => $product->id,
        'product_variant_id' => null,
        'quantity' => 1,
    ])->assertSessionHasErrors('product_id');

    expect(session('guest_cart', []))->toBeEmpty();
});

test('checkout total discards null and foreign variants while accepting a simple product', function () {
    $owner = User::factory()->create();
    $store = cartCheckoutSecurityStore($owner, 'Checkout Store');
    $simpleProduct = cartCheckoutSecurityProduct($store, 'Simple', 1500);
    $variantProduct = cartCheckoutSecurityProduct($store, 'Variant', 3000);
    $variant = $variantProduct->variants()->create([
        'options' => ['Size' => 'L'],
        'price' => 4000,
        'stock' => 5,
    ]);

    $request = Request::create('/checkout', 'GET');
    $request->setLaravelSession(app('session')->driver());
    $request->session()->put('guest_cart', [
        'valid-simple' => ['product_id' => $simpleProduct->id, 'product_variant_id' => null, 'quantity' => 2],
        'missing-variant' => ['product_id' => $variantProduct->id, 'product_variant_id' => null, 'quantity' => 1],
        'foreign-variant' => ['product_id' => $simpleProduct->id, 'product_variant_id' => $variant->id, 'quantity' => 1],
    ]);

    $total = app(CheckoutController::class)->calculateCartTotal($request, $store);

    expect($total)->toEqual(3000);
});

test('checkout validates whatsapp before creating an order and preserves the session cart', function () {
    $owner = User::factory()->create();
    $store = cartCheckoutSecurityStore($owner, 'No Phone Store', 'invalid');
    $product = cartCheckoutSecurityProduct($store, 'Simple');
    $cart = [
        'simple' => [
            'product_id' => $product->id,
            'product_variant_id' => null,
            'quantity' => 1,
            'store_id' => $store->id,
        ],
    ];

    $this->withSession(['guest_cart' => $cart])
        ->from(route('checkout.index', $store))
        ->post(route('checkout.store', $store), [
            'customer_name' => 'Secure Customer',
            'customer_phone' => '3001234567',
            'customer_email' => 'secure@example.com',
            'customer_address' => 'Main Street 1',
            'idempotency_key' => (string) Str::uuid(),
        ])
        ->assertSessionHasErrors('phone');

    $this->assertDatabaseCount('orders', 0);
    expect(session('guest_cart'))->toBe($cart);
});

test('checkout rejects an address belonging to another customer', function () {
    $owner = User::factory()->create();
    $store = cartCheckoutSecurityStore($owner, 'Address Store');
    $product = cartCheckoutSecurityProduct($store, 'Simple');
    $customer = Customer::create([
        'store_id' => $store->id,
        'name' => 'Customer One',
        'email' => 'one@example.com',
        'password' => 'password',
        'phone' => '3001234567',
    ]);
    $otherCustomer = Customer::create([
        'store_id' => $store->id,
        'name' => 'Customer Two',
        'email' => 'two@example.com',
        'password' => 'password',
        'phone' => '3007654321',
    ]);
    $foreignAddress = $otherCustomer->addresses()->create([
        'address_line_1' => 'Other Street 2',
        'city' => 'Bogota',
    ]);

    $this->actingAs($customer, 'customer')
        ->withSession(['guest_cart' => [
            'simple' => [
                'product_id' => $product->id,
                'product_variant_id' => null,
                'quantity' => 1,
                'store_id' => $store->id,
            ],
        ]])
        ->post(route('checkout.store', $store), [
            'customer_name' => 'Customer One',
            'customer_phone' => '3001234567',
            'customer_email' => 'one@example.com',
            'customer_address' => 'Main Street 1',
            'address_id' => $foreignAddress->id,
            'idempotency_key' => (string) Str::uuid(),
        ])
        ->assertSessionHasErrors('address_id');

    $this->assertDatabaseCount('orders', 0);
});

test('checkout replays a guest order once and preserves carts from other stores', function () {
    $owner = User::factory()->create();
    $firstStore = cartCheckoutSecurityStore($owner, 'First Idempotent Store');
    $secondStore = cartCheckoutSecurityStore($owner, 'Second Idempotent Store');
    $firstProduct = cartCheckoutSecurityProduct($firstStore, 'First Product', 1500);
    $secondProduct = cartCheckoutSecurityProduct($secondStore, 'Second Product', 2500);
    $idempotencyKey = (string) Str::uuid();
    $payload = [
        'customer_name' => 'Guest Customer',
        'customer_phone' => '3001234567',
        'customer_email' => 'guest@example.com',
        'customer_address' => 'Main Street 1',
        'idempotency_key' => $idempotencyKey,
    ];

    $this->withSession(['guest_cart' => [
        'first' => [
            'product_id' => $firstProduct->id,
            'product_variant_id' => null,
            'quantity' => 1,
            'store_id' => $firstStore->id,
        ],
        'second' => [
            'product_id' => $secondProduct->id,
            'product_variant_id' => null,
            'quantity' => 1,
            'store_id' => $secondStore->id,
        ],
    ]]);

    $firstResponse = $this->post(route('checkout.store', $firstStore), $payload);
    $replayResponse = $this->post(route('checkout.store', $firstStore), $payload);

    $firstResponse->assertRedirect();
    $replayResponse->assertRedirect($firstResponse->headers->get('Location'));
    $this->assertDatabaseCount('orders', 1);
    $this->assertDatabaseCount('order_items', 1);
    expect($firstStore->fresh()->order_sequence)->toBe(1)
        ->and(session('guest_cart'))->toHaveKey('second')
        ->not->toHaveKey('first');

    $this->post(route('checkout.store', $secondStore), $payload)->assertRedirect();

    $this->assertDatabaseCount('orders', 2);
    expect($secondStore->fresh()->order_sequence)->toBe(1);
});

test('checkout idempotency consumes a customer coupon only once', function () {
    $owner = User::factory()->create();
    $store = cartCheckoutSecurityStore($owner, 'Coupon Idempotent Store');
    $product = cartCheckoutSecurityProduct($store, 'Coupon Product', 5000);
    $customer = Customer::create([
        'store_id' => $store->id,
        'name' => 'Coupon Customer',
        'email' => 'coupon@example.com',
        'password' => 'password',
        'phone' => '3001234567',
    ]);
    $coupon = Coupon::create([
        'store_id' => $store->id,
        'code' => 'ONCE10',
        'type' => 'percentage',
        'value' => 10,
        'usage_limit' => 1,
        'usage_limit_per_customer' => 1,
        'is_active' => true,
    ]);
    $payload = [
        'customer_name' => $customer->name,
        'customer_phone' => $customer->phone,
        'customer_email' => $customer->email,
        'customer_address' => 'Main Street 1',
        'coupon_code' => $coupon->code,
        'idempotency_key' => (string) Str::uuid(),
    ];

    $this->actingAs($customer, 'customer')->withSession(['guest_cart' => [
        'product' => [
            'product_id' => $product->id,
            'product_variant_id' => null,
            'quantity' => 1,
            'store_id' => $store->id,
        ],
    ]]);

    $firstResponse = $this->post(route('checkout.store', $store), $payload);
    $replayResponse = $this->post(route('checkout.store', $store), $payload);

    $replayResponse->assertRedirect($firstResponse->headers->get('Location'));
    $this->assertDatabaseCount('orders', 1);
    $this->assertDatabaseCount('coupon_usages', 1);
    $this->assertDatabaseHas('orders', [
        'store_id' => $store->id,
        'idempotency_key' => $payload['idempotency_key'],
        'discount_amount' => 500,
    ]);
});

test('checkout requires a valid idempotency key', function () {
    $owner = User::factory()->create();
    $store = cartCheckoutSecurityStore($owner, 'Invalid Idempotency Store');
    $product = cartCheckoutSecurityProduct($store, 'Simple');

    $this->withSession(['guest_cart' => [
        'simple' => [
            'product_id' => $product->id,
            'product_variant_id' => null,
            'quantity' => 1,
            'store_id' => $store->id,
        ],
    ]])->post(route('checkout.store', $store), [
        'customer_name' => 'Guest Customer',
        'customer_phone' => '3001234567',
        'customer_email' => 'guest@example.com',
        'customer_address' => 'Main Street 1',
        'idempotency_key' => 'not-a-uuid',
    ])->assertSessionHasErrors('idempotency_key');

    $this->assertDatabaseCount('orders', 0);
});
