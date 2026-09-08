<?php

use App\Models\Store;
use App\Models\User;

function authenticationTestStore(User $user): Store
{
    $store = Store::create([
        'name' => 'Authentication Store '.$user->id,
        'user_id' => $user->id,
    ]);
    $user->update(['store_id' => $store->id]);

    return $store;
}

test('login screen can be rendered', function () {
    $response = $this->get('/login');

    $response->assertStatus(200);
});

test('users can authenticate using the login screen', function () {
    $user = User::factory()->create();
    $store = authenticationTestStore($user);

    $response = $this->post('/login', [
        'store_name' => $store->name,
        'email' => $user->email,
        'password' => 'password',
    ]);

    $this->assertAuthenticated();
    $response->assertRedirect(route('dashboard', absolute: false));
});

test('users can not authenticate with invalid password', function () {
    $user = User::factory()->create();
    $store = authenticationTestStore($user);

    $this->post('/login', [
        'store_name' => $store->name,
        'email' => $user->email,
        'password' => 'wrong-password',
    ]);

    $this->assertGuest();
});

test('users can logout', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->post('/logout');

    $this->assertGuest();
    $response->assertRedirect('/login');
});
