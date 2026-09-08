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

it('only shares customer data inside the customer store catalog', function () {
    [$customerStore] = createSecurityTestStore('customer-context-store');
    [$otherStore] = createSecurityTestStore('other-context-store');
    $customer = Customer::create([
        'store_id' => $customerStore->id,
        'name' => 'Context Customer',
        'email' => 'context@example.test',
        'password' => 'password',
    ]);
    $customer->addresses()->create([
        'address_line_1' => 'Main Street 1',
        'city' => 'Bogota',
        'is_default' => true,
    ]);
    $customer->notifications()->create([
        'type' => 'general',
        'title' => 'Private notification',
        'message' => 'Only for the matching store.',
    ]);

    $this->actingAs($customer, 'customer')
        ->get(route('catalogo.index', $customerStore))
        ->assertInertia(fn (Assert $page) => $page
            ->where('customer.user.id', $customer->id)
            ->where('customer.defaultAddress.city', 'Bogota')
            ->where('customer.notificationsCount', 1));

    $this->get(route('catalogo.index', $otherStore))
        ->assertInertia(fn (Assert $page) => $page
            ->where('customer.user', null)
            ->where('customer.defaultAddress', null)
            ->where('customer.notifications', [])
            ->where('customer.notificationsCount', 0));
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

it('searches catalog content and applies price and availability filters', function () {
    [$store] = createSecurityTestStore('catalog-filter-store');
    $category = Category::create(['name' => 'Café especial', 'store_id' => $store->id]);
    $matching = $store->products()->create([
        'category_id' => $category->id,
        'name' => 'Edición de temporada',
        'price' => 35000,
        'short_description' => 'Café cultivado en altura',
        'track_inventory' => true,
        'quantity' => 4,
        'is_active' => true,
    ]);
    $store->products()->create([
        'category_id' => $category->id,
        'name' => 'Producto agotado',
        'price' => 25000,
        'short_description' => 'También cultivado en altura',
        'track_inventory' => true,
        'quantity' => 0,
        'is_active' => true,
    ]);
    $store->products()->create([
        'category_id' => $category->id,
        'name' => 'Producto costoso',
        'price' => 90000,
        'short_description' => 'También cultivado en altura',
        'track_inventory' => false,
        'is_active' => true,
    ]);

    $this->get(route('catalogo.index', $store).'?search=altura&min_price=30000&max_price=40000&availability=in_stock')
        ->assertInertia(fn (Assert $page) => $page
            ->has('products.data', 1)
            ->where('products.data.0.id', $matching->id)
            ->where('products.data.0.has_variants', false)
            ->where('filters.availability', 'in_stock'));
});

it('counts only active products in public categories', function () {
    [$store] = createSecurityTestStore('active-category-store');
    $parent = Category::create(['name' => 'Parent', 'store_id' => $store->id]);
    $child = Category::create(['name' => 'Child', 'store_id' => $store->id, 'parent_id' => $parent->id]);
    $store->products()->create([
        'category_id' => $child->id,
        'name' => 'Visible product',
        'price' => 100,
        'is_active' => true,
    ]);
    $store->products()->create([
        'category_id' => $child->id,
        'name' => 'Hidden product',
        'price' => 100,
        'is_active' => false,
    ]);

    $this->get(route('catalogo.index', $store))
        ->assertInertia(fn (Assert $page) => $page
            ->where('categories.0.id', $parent->id)
            ->where('categories.0.products_count', 1)
            ->where('categories.0.has_children_with_products', true));
});

it('renders social metadata for store and product crawlers', function () {
    [$store] = createSecurityTestStore('social-metadata-store');
    $category = Category::create(['name' => 'Social', 'store_id' => $store->id]);
    $product = $store->products()->create([
        'category_id' => $category->id,
        'name' => 'Shareable product',
        'price' => 100,
        'short_description' => 'Description for social networks',
        'is_active' => true,
    ]);

    $this->withHeader('User-Agent', 'WhatsApp/2.0')
        ->get(route('catalogo.index', $store))
        ->assertOk()
        ->assertSee('og:site_name', false)
        ->assertSee($store->name);

    $this->withHeader('User-Agent', 'facebookexternalhit/1.1')
        ->get(route('catalogo.show', [$store, $product]))
        ->assertOk()
        ->assertSee('og:type', false)
        ->assertSee($product->name);
});
