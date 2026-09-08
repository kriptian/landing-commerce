<?php

use App\Models\Category;
use App\Models\Customer;
use App\Models\Store;
use App\Models\User;
use Illuminate\Support\Facades\Route;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Permission;

function createSecurityTestStore(string $slug, string $plan = 'negociante'): array
{
    $user = User::factory()->create();
    $store = Store::create([
        'name' => ucfirst($slug),
        'slug' => $slug,
        'user_id' => $user->id,
        'plan' => $plan,
    ]);
    $user->update(['store_id' => $store->id]);

    return [$store, $user];
}

it('does not expose the storage link command route', function () {
    expect(collect(Route::getRoutes())->contains(
        fn ($route) => $route->uri() === 'crear-link-de-almacenamiento'
    ))->toBeFalse();

    expect(Route::getRoutes()->getByName('admin.orders.confirm'))->toBeNull();
});

it('adds defensive headers to web responses', function () {
    $response = $this->get('/')
        ->assertOk()
        ->assertHeader('X-Content-Type-Options', 'nosniff')
        ->assertHeader('X-Frame-Options', 'SAMEORIGIN')
        ->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin')
        ->assertHeader('Permissions-Policy', 'camera=(), microphone=(), geolocation=()');

    $policy = $response->headers->get('Content-Security-Policy');

    expect($policy)
        ->toContain("default-src 'self'")
        ->toContain("object-src 'none'")
        ->toContain("base-uri 'self'")
        ->toContain("form-action 'self'")
        ->toContain("frame-ancestors 'self'")
        ->not->toContain("script-src 'self' 'unsafe-inline'");

    preg_match("/script-src 'self' 'nonce-([^']+)'/", $policy, $matches);

    expect($matches[1] ?? null)->not->toBeNull()
        ->and($response->getContent())->toContain('nonce="'.$matches[1].'"');
});

it('prevents an authenticated customer from accessing another store area', function () {
    [$customerStore] = createSecurityTestStore('customer-store');
    [$otherStore] = createSecurityTestStore('other-store');
    $customer = Customer::create([
        'store_id' => $customerStore->id,
        'name' => 'Customer',
        'email' => 'customer@example.test',
        'password' => 'password',
    ]);

    $this->actingAs($customer, 'customer')
        ->get(route('customer.account', ['store' => $otherStore->slug]))
        ->assertForbidden();
});

it('regenerates the session after customer registration', function () {
    [$store] = createSecurityTestStore('registration-store');
    $this->withSession(['security-marker' => true]);
    $oldSessionId = session()->getId();

    $this->post(route('customer.register.store', ['store' => $store->slug]), [
        'name' => 'New Customer',
        'email' => 'new@example.test',
        'password' => 'secure-password',
        'password_confirmation' => 'secure-password',
    ])->assertRedirect(route('catalogo.index', ['store' => $store->slug]));

    $this->assertAuthenticated('customer');
    expect(session()->getId())->not->toBe($oldSessionId);
});

it('applies named throttles only to public write routes', function () {
    expect(Route::getRoutes()->getByName('customer.register.store')->gatherMiddleware())
        ->toContain('throttle:customer-registration')
        ->and(Route::getRoutes()->getByName('customer.login.store')->gatherMiddleware())
        ->toContain('throttle:customer-login')
        ->and(Route::getRoutes()->getByName('cart.store')->gatherMiddleware())
        ->toContain('throttle:customer-cart')
        ->and(Route::getRoutes()->getByName('checkout.store')->gatherMiddleware())
        ->toContain('throttle:customer-checkout')
        ->and(Route::getRoutes()->getByName('checkout.validate-coupon')->gatherMiddleware())
        ->toContain('throttle:checkout-coupon')
        ->and(Route::getRoutes()->getByName('catalogo.index')->gatherMiddleware())
        ->not->toContain('throttle:customer-cart');
});

it('rejects unsafe popup button links', function (string $link) {
    [$store, $user] = createSecurityTestStore('popup-store');
    $user->givePermissionTo(Permission::findOrCreate('gestionar galeria', 'web'));

    $this->actingAs($user)->put(route('admin.catalog-customization.update'), [
        'gallery_type' => 'products',
        'gallery_show_buy_button' => true,
        'catalog_use_default' => true,
        'catalog_product_template' => 'default',
        'catalog_show_buy_button' => true,
        'catalog_header_style' => 'default',
        'delivery_cost_active' => false,
        'popup_button_link' => $link,
        'popup_frequency' => 'session',
    ])->assertSessionHasErrors('popup_button_link');

    expect($store->fresh()->popup_button_link)->toBeNull();
})->with([
    'javascript scheme' => 'javascript:alert(1)',
    'data scheme' => 'data:text/html,<script>alert(1)</script>',
    'protocol-relative URL' => '//evil.example/path',
]);

it('does not expose purchase prices in public catalog responses', function () {
    [$store] = createSecurityTestStore('public-price-store');
    $category = Category::create(['name' => 'Public category', 'store_id' => $store->id]);
    $product = $store->products()->create([
        'category_id' => $category->id,
        'name' => 'Public product',
        'price' => 200,
        'purchase_price' => 25,
        'is_active' => true,
    ]);
    $product->variants()->create([
        'options' => ['Color' => 'Blue'],
        'price' => 200,
        'purchase_price' => 20,
        'stock' => 5,
    ]);

    $this->get(route('catalogo.index', $store))
        ->assertInertia(fn (Assert $page) => $page
            ->missing('products.data.0.purchase_price'));

    $this->get(route('catalogo.show', [$store, $product]))
        ->assertInertia(fn (Assert $page) => $page
            ->missing('product.purchase_price')
            ->missing('product.variants.0.purchase_price'));
});
