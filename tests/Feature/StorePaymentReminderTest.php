<?php

use App\Models\Product;
use App\Models\Category;
use App\Models\Store;
use App\Models\StorePaymentReminder;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Permission;

function createReminderStore(string $name): array
{
    $owner = User::factory()->create();
    $store = Store::create([
        'name' => $name,
        'user_id' => $owner->id,
        'plan' => 'negociante',
    ]);
    $owner->update(['store_id' => $store->id]);

    return [$owner->fresh(), $store->fresh()];
}

function configureReminderSuperAdmin(): User
{
    $superAdmin = User::factory()->create(['email' => 'billing-admin@example.test']);
    config()->set('app.super_admin_email', $superAdmin->email);
    config()->set('app.super_admin_emails', []);

    return $superAdmin;
}

it('allows only the super administrator to configure a store payment reminder', function () {
    Storage::fake('local');
    [$owner, $store] = createReminderStore('Reminder tenant');
    $payload = [
        'message' => 'Tienes un pago pendiente.',
        'mode' => 'repeatable',
        'repeat_interval' => 3,
        'repeat_unit' => 'hours',
        'pause_catalog' => true,
        'active' => true,
        'image' => UploadedFile::fake()->image('qr.png'),
    ];

    $this->actingAs($owner)
        ->post(route('super.stores.payment-reminder.update', $store), $payload)
        ->assertForbidden();

    $superAdmin = configureReminderSuperAdmin();
    $this->actingAs($superAdmin)
        ->post(route('super.stores.payment-reminder.update', $store), $payload)
        ->assertRedirect();

    $reminder = $store->paymentReminder()->firstOrFail();
    expect($reminder->message)->toBe('Tienes un pago pendiente.')
        ->and($reminder->mode)->toBe('repeatable')
        ->and($reminder->repeat_interval)->toBe(3)
        ->and($reminder->repeat_unit)->toBe('hours')
        ->and($reminder->pause_catalog)->toBeTrue()
        ->and($reminder->active)->toBeTrue();
    Storage::disk('local')->assertExists($reminder->image_path);
});

it('shares an active reminder only with administrators from the target store', function () {
    [$owner, $store] = createReminderStore('Target reminder');
    [$otherOwner] = createReminderStore('Other reminder');
    Permission::findOrCreate('ver dashboard');
    $owner->givePermissionTo('ver dashboard');
    $otherOwner->givePermissionTo('ver dashboard');
    $store->paymentReminder()->create([
        'message' => 'Mensaje privado de cobro',
        'mode' => 'persistent',
        'pause_catalog' => false,
        'active' => true,
        'activated_at' => now(),
    ]);

    $this->actingAs($owner)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('paymentReminder.message', 'Mensaje privado de cobro')
            ->where('paymentReminder.mode', 'persistent'));

    $this->actingAs($otherOwner)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('paymentReminder', null));
});

it('keeps reminder images private to the target store and super administrator', function () {
    Storage::fake('local');
    [$owner, $store] = createReminderStore('Private QR');
    [$otherOwner] = createReminderStore('Other QR');
    $path = UploadedFile::fake()->image('qr.png')->store("payment-reminders/{$store->id}", 'local');
    $store->paymentReminder()->create([
        'message' => 'Paga con este QR',
        'image_path' => $path,
        'mode' => 'persistent',
        'active' => true,
        'activated_at' => now(),
    ]);

    $this->actingAs($owner)->get(route('admin.payment-reminder.image'))->assertOk();
    $this->actingAs($otherOwner)->get(route('admin.payment-reminder.image'))->assertNotFound();

    $superAdmin = configureReminderSuperAdmin();
    $this->actingAs($superAdmin)
        ->get(route('super.stores.payment-reminder.image', $store))
        ->assertOk();
});

it('pauses the public shopping flow without exposing the payment reason', function () {
    [, $store] = createReminderStore('Paused catalog');
    $category = Category::create(['name' => 'Paused category', 'store_id' => $store->id]);
    $product = $store->products()->create([
        'name' => 'Paused product',
        'price' => 10000,
        'quantity' => 5,
        'track_inventory' => true,
        'is_active' => true,
        'category_id' => $category->id,
    ]);
    $store->paymentReminder()->create([
        'message' => 'Mensaje privado que no debe ser público',
        'mode' => 'persistent',
        'pause_catalog' => true,
        'active' => true,
        'activated_at' => now(),
    ]);

    $this->get(route('catalogo.index', $store->slug))
        ->assertStatus(423)
        ->assertInertia(fn (Assert $page) => $page
            ->component('Public/CatalogUnavailable')
            ->where('store.name', $store->name)
            ->missing('paymentReminder.message'));

    $this->get(route('catalogo.show', [$store->slug, $product]))
        ->assertStatus(423)
        ->assertInertia(fn (Assert $page) => $page->component('Public/CatalogUnavailable'));

    $this->from(route('catalogo.index', $store->slug))
        ->post(route('cart.store'), [
            'product_id' => $product->id,
            'quantity' => 1,
        ])
        ->assertRedirect(route('catalogo.index', $store->slug))
        ->assertSessionHasErrors('catalog');

    $this->get(route('checkout.index', $store->slug))->assertStatus(423);
});

it('reactivates the catalog automatically when payment is confirmed', function () {
    [, $store] = createReminderStore('Restored catalog');
    $store->paymentReminder()->create([
        'message' => 'Pago pendiente',
        'mode' => 'persistent',
        'pause_catalog' => true,
        'active' => true,
        'activated_at' => now(),
    ]);
    $superAdmin = configureReminderSuperAdmin();

    $this->actingAs($superAdmin)
        ->delete(route('super.stores.payment-reminder.destroy', $store))
        ->assertRedirect();

    expect($store->paymentReminder()->first()->active)->toBeFalse()
        ->and($store->fresh()->isCatalogPaused())->toBeFalse();

    $this->get(route('catalogo.index', $store->slug))->assertOk();
});
