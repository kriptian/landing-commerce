<?php

test('registration screen can be rendered', function () {
    $response = $this->get('/register');

    $response->assertStatus(200);
});

test('new users can register', function () {
    $response = $this->post('/register', [
        'name' => 'Test User',
        'store_name' => 'Test Store',
        'email' => 'test@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
    ]);

    $this->assertGuest();
    $response->assertSessionHas('store_created');
    $this->assertDatabaseHas('stores', [
        'name' => 'Test Store',
        'plan' => 'emprendedor',
        'max_users' => 1,
    ]);
});
