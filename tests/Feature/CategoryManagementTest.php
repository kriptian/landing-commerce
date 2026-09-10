<?php

use App\Models\Store;
use App\Models\User;
use Illuminate\Database\QueryException;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Permission;

function categoryTenant(string $name): array
{
    $user = User::factory()->create();
    $store = Store::create([
        'name' => $name,
        'user_id' => $user->id,
        'plan' => 'emprendedor',
    ]);
    $user->update(['store_id' => $store->id]);

    return [$user->fresh(), $store];
}

test('a category creator can create but cannot manage categories', function () {
    [$user, $store] = categoryTenant('Limited category tenant');
    $user->givePermissionTo(Permission::findOrCreate('crear categorias'));

    $this->actingAs($user)
        ->postJson(route('admin.categories.store'), ['name' => 'Ropa'])
        ->assertCreated()
        ->assertJsonPath('category.name', 'Ropa');

    $this->actingAs($user)->get(route('admin.categories.index'))->assertForbidden();
    $this->assertDatabaseHas('categories', ['store_id' => $store->id, 'name' => 'Ropa']);
});

test('bulk category creation rolls back when a sibling name is duplicated', function () {
    [$user, $store] = categoryTenant('Transactional category tenant');
    $user->givePermissionTo(Permission::findOrCreate('crear categorias'));

    $this->actingAs($user)->postJson(route('admin.categories.store'), [
        'name' => 'Ropa',
        'subcategories' => [
            ['name' => 'Mujer'],
            ['name' => 'Mujer'],
        ],
    ])->assertUnprocessable();

    expect($store->categories()->count())->toBe(0);
});

test('categories are limited to three levels', function () {
    [$user, $store] = categoryTenant('Depth category tenant');
    $user->givePermissionTo(Permission::findOrCreate('crear categorias'));
    $root = $store->categories()->create(['name' => 'Ropa']);
    $child = $store->categories()->create(['name' => 'Mujer', 'parent_id' => $root->id]);
    $leaf = $store->categories()->create(['name' => 'Camisetas', 'parent_id' => $child->id]);

    $this->actingAs($user)
        ->postJson(route('admin.categories.storeSubcategory', $leaf), ['name' => 'Manga corta'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('name');

    $this->assertDatabaseMissing('categories', ['store_id' => $store->id, 'name' => 'Manga corta']);
});

test('category mutations cannot cross tenant boundaries', function () {
    [$user] = categoryTenant('First category tenant');
    [, $otherStore] = categoryTenant('Other category tenant');
    $foreignCategory = $otherStore->categories()->create(['name' => 'Privada']);
    $user->givePermissionTo(Permission::findOrCreate('gestionar categorias'));

    $this->actingAs($user)
        ->put(route('admin.categories.update', $foreignCategory), ['name' => 'Alterada'])
        ->assertNotFound();
    $this->actingAs($user)
        ->delete(route('admin.categories.destroy', $foreignCategory))
        ->assertNotFound();

    expect($foreignCategory->fresh()->name)->toBe('Privada');
});

test('a category with products cannot be deleted', function () {
    [$user, $store] = categoryTenant('Protected product tenant');
    $user->givePermissionTo(Permission::findOrCreate('gestionar categorias'));
    $category = $store->categories()->create(['name' => 'Protegida']);
    $product = $store->products()->create([
        'name' => 'Producto protegido',
        'price' => 100,
        'category_id' => $category->id,
        'quantity' => 1,
        'track_inventory' => true,
        'is_active' => true,
    ]);

    $this->actingAs($user)
        ->from(route('admin.categories.index'))
        ->delete(route('admin.categories.destroy', $category))
        ->assertRedirect(route('admin.categories.index'))
        ->assertSessionHasErrors('delete');

    $this->assertDatabaseHas('products', ['id' => $product->id, 'category_id' => $category->id]);
    $this->assertDatabaseHas('categories', ['id' => $category->id]);
});

test('category index exposes paths depths and subtree totals', function () {
    [$user, $store] = categoryTenant('Category tree tenant');
    $user->givePermissionTo(Permission::findOrCreate('gestionar categorias'));
    $root = $store->categories()->create(['name' => 'Ropa']);
    $child = $store->categories()->create(['name' => 'Mujer', 'parent_id' => $root->id]);
    $leaf = $store->categories()->create(['name' => 'Camisetas', 'parent_id' => $child->id]);
    $store->products()->create([
        'name' => 'Camiseta',
        'price' => 50,
        'category_id' => $leaf->id,
        'quantity' => 2,
        'track_inventory' => true,
        'is_active' => true,
    ]);

    $this->actingAs($user)->get(route('admin.categories.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Categories/Index')
            ->where('categories.0.path', 'Ropa')
            ->where('categories.0.depth', 1)
            ->where('categories.0.descendants_count', 2)
            ->where('categories.0.subtree_products_count', 1)
            ->where('categories.0.children.0.children.0.path', 'Ropa > Mujer > Camisetas')
            ->where('categories.0.children.0.children.0.is_selectable', true));
});

test('database foreign key rejects deleting a category used by a product', function () {
    [, $store] = categoryTenant('Foreign key category tenant');
    $category = $store->categories()->create(['name' => 'En uso']);
    $store->products()->create([
        'name' => 'Producto',
        'price' => 10,
        'category_id' => $category->id,
        'quantity' => 1,
        'track_inventory' => true,
        'is_active' => true,
    ]);

    expect(fn () => $category->delete())->toThrow(QueryException::class);
});
