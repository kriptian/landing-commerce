<?php

use App\Models\Store;
use App\Models\User;

function createPlanTenant(): array
{
    $user = User::factory()->create();
    $store = Store::create([
        'name' => 'Plan tenant',
        'user_id' => $user->id,
        'plan' => 'emprendedor',
        'max_users' => 1,
    ]);
    $user->update(['store_id' => $store->id]);

    return [$user->fresh(), $store];
}

test('store setup ignores client supplied plan authority', function () {
    [$user, $store] = createPlanTenant();

    $this->actingAs($user)->post(route('store.save'), [
        'name' => 'Renamed tenant',
        'plan' => 'negociante',
        'plan_cycle' => 'anual',
        'max_users' => 999,
    ])->assertRedirect(route('dashboard'));

    $store->refresh();
    expect($store->name)->toBe('Renamed tenant')
        ->and($store->plan)->toBe('emprendedor')
        ->and($store->max_users)->toBe(1);
});

test('upgrade endpoint does not grant a paid plan without payment', function () {
    [$user, $store] = createPlanTenant();

    $this->actingAs($user)
        ->from(route('dashboard'))
        ->post(route('store.upgrade'))
        ->assertRedirect(route('dashboard'))
        ->assertSessionHasErrors('plan');

    expect($store->fresh()->plan)->toBe('emprendedor');
});

test('public registration cannot select a paid plan or increase its user quota', function () {
    $this->post('/register', [
        'name' => 'Secure Owner',
        'store_name' => 'Secure Registered Store',
        'email' => 'secure-owner@example.test',
        'password' => 'secure-password',
        'password_confirmation' => 'secure-password',
        'plan' => 'negociante',
        'plan_cycle' => 'anual',
        'max_users' => 999,
    ])->assertSessionHasNoErrors();

    $store = Store::where('name', 'Secure Registered Store')->firstOrFail();
    expect($store->plan)->toBe('emprendedor')
        ->and($store->plan_cycle)->toBe('mensual')
        ->and($store->max_users)->toBe(1);
});
