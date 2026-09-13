<?php

use App\Models\Customer;
use App\Models\Store;
use App\Models\User;
use App\Support\ColombiaDivipola;

function divipolaCustomer(): array
{
    $owner = User::factory()->create();
    $store = Store::create([
        'name' => 'Address Locations Store',
        'slug' => 'address-locations-store',
        'user_id' => $owner->id,
        'plan' => 'negociante',
    ]);
    $customer = Customer::create([
        'store_id' => $store->id,
        'name' => 'Address Customer',
        'email' => 'address@example.com',
        'password' => 'password',
    ]);

    return [$store, $customer];
}

test('local divipola catalog is complete and keeps codes as strings', function () {
    $catalog = ColombiaDivipola::catalog();
    $municipalities = collect($catalog['departments'])->sum(fn (array $department) => count($department['municipalities']));

    expect($catalog['departments'])->toHaveCount(33)
        ->and($municipalities)->toBe(1122)
        ->and($catalog['departments'][0]['code'])->toBeString()
        ->and($catalog['departments'][0]['municipalities'][0]['code'])->toBeString();
});

test('customer address resolves official names from valid codes', function () {
    [$store, $customer] = divipolaCustomer();

    $this->actingAs($customer, 'customer')->post(route('customer.addresses.store', $store), [
        'label' => 'Casa',
        'address_line_1' => 'Calle 80 # 10-20',
        'department_code' => '05',
        'municipality_code' => '05001',
        'department_name' => 'Manipulado',
        'municipality_name' => 'Manipulado',
    ])->assertSessionHasNoErrors();

    $this->assertDatabaseHas('addresses', [
        'customer_id' => $customer->id,
        'department_code' => '05',
        'department_name' => 'ANTIOQUIA',
        'municipality_code' => '05001',
        'municipality_name' => 'MEDELLÍN',
        'city' => 'MEDELLÍN',
        'state' => 'ANTIOQUIA',
        'country' => 'Colombia',
    ]);
});

test('customer address rejects a municipality outside its department', function () {
    [$store, $customer] = divipolaCustomer();

    $this->actingAs($customer, 'customer')->post(route('customer.addresses.store', $store), [
        'label' => 'Casa',
        'address_line_1' => 'Calle 80 # 10-20',
        'department_code' => '05',
        'municipality_code' => '11001',
    ])->assertSessionHasErrors('municipality_code');

    $this->assertDatabaseCount('addresses', 0);
});
