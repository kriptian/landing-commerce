<?php

use App\Models\Store;
use App\Models\User;
use Laravel\Dusk\Browser;
use Spatie\Permission\Models\Permission;

test('admin navigation adapts between desktop sidebar and mobile drawer', function () {
    $owner = User::factory()->create();
    $store = Store::create([
        'name' => 'Dusk Admin Store',
        'slug' => 'dusk-admin-store',
        'user_id' => $owner->id,
        'plan' => 'emprendedor',
    ]);
    $owner->update(['store_id' => $store->id]);
    $owner->givePermissionTo([
        Permission::findOrCreate('ver dashboard'),
        Permission::findOrCreate('ver inventario'),
        Permission::findOrCreate('crear productos'),
        Permission::findOrCreate('gestionar categorias'),
    ]);

    $this->browse(function (Browser $browser) use ($owner) {
        $browser->resize(1440, 900)
            ->loginAs($owner)
            ->visitRoute('dashboard')
            ->waitFor('@admin-sidebar')
            ->assertVisible('@admin-sidebar')
            ->assertSee('Productos')
            ->assertSee('Categorias')
            ->assertDontSee('Usuarios y roles')
            ->resize(390, 844)
            ->pause(250)
            ->assertVisible('@admin-mobile-trigger')
            ->click('@admin-mobile-trigger')
            ->waitFor('@admin-mobile-drawer')
            ->assertVisible('@admin-mobile-drawer')
            ->assertSee('Punto de venta')
            ->assertScript('document.documentElement.scrollWidth <= document.documentElement.clientWidth', true)
            ->script("document.dispatchEvent(new KeyboardEvent('keydown', { key: 'Escape' }))");

        $browser
            ->waitUntilMissing('@admin-mobile-drawer')
            ->resize(1440, 900)
            ->visitRoute('admin.products.create')
            ->waitForText('Prepara tu producto para vender')
            ->assertSee('Revisa lo esencial')
            ->assertSee('Informacion principal')
            ->assertSee('Opciones del producto')
            ->resize(390, 844)
            ->pause(250)
            ->assertScript('document.documentElement.scrollWidth <= document.documentElement.clientWidth', true);
    });
});
