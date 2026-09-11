<?php

use App\Models\Store;
use App\Models\User;
use Laravel\Dusk\Browser;
use Spatie\Permission\Models\Permission;

function createReminderBrowserOwner(string $name): array
{
    $owner = User::factory()->create();
    $store = Store::create([
        'name' => $name,
        'user_id' => $owner->id,
        'plan' => 'emprendedor',
    ]);
    $owner->update(['store_id' => $store->id]);
    $owner->givePermissionTo(Permission::findOrCreate('ver dashboard'));

    return [$owner->fresh(), $store->fresh()];
}

test('a repeatable payment reminder can be dismissed by the store administrator', function () {
    [$owner, $store] = createReminderBrowserOwner('Repeatable Reminder Store');
    $store->paymentReminder()->create([
        'message' => 'Este es un recordatorio repetible de prueba.',
        'mode' => 'repeatable',
        'repeat_interval' => 1,
        'repeat_unit' => 'hours',
        'active' => true,
        'activated_at' => now(),
    ]);

    $this->browse(function (Browser $browser) use ($owner) {
        $browser->loginAs($owner)
            ->visitRoute('dashboard')
            ->waitFor('@payment-reminder')
            ->assertSee('Este es un recordatorio repetible de prueba.')
            ->click('@payment-reminder-dismiss')
            ->waitUntilMissing('@payment-reminder');
    });
});

test('a persistent payment reminder cannot be dismissed by the store administrator', function () {
    [$owner, $store] = createReminderBrowserOwner('Persistent Reminder Store');
    $store->paymentReminder()->create([
        'message' => 'Este recordatorio requiere atencion.',
        'mode' => 'persistent',
        'active' => true,
        'activated_at' => now(),
    ]);

    $this->browse(function (Browser $browser) use ($owner) {
        $browser->loginAs($owner)
            ->visitRoute('dashboard')
            ->waitFor('@payment-reminder')
            ->assertSee('Este recordatorio requiere atencion.')
            ->assertMissing('@payment-reminder-dismiss')
            ->assertMissing('@payment-reminder-close')
            ->assertSee('Cerrar sesion');
    });
});
