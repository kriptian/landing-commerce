<?php

use App\Models\Role;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    private const CREATE_PERMISSION = 'crear categorias';

    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropForeign(['category_id']);
            $table->foreign('category_id')->references('id')->on('categories')->restrictOnDelete();
        });

        if (Schema::hasTable('permissions') && Schema::hasTable('roles')) {
            $permission = Permission::firstOrCreate([
                'name' => self::CREATE_PERMISSION,
                'guard_name' => 'web',
            ]);
            Role::query()
                ->where('name', 'Administrador')
                ->each(fn (Role $role) => $role->givePermissionTo($permission));
            app(PermissionRegistrar::class)->forgetCachedPermissions();
        }
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropForeign(['category_id']);
            $table->foreign('category_id')->references('id')->on('categories')->cascadeOnDelete();
        });

        if (Schema::hasTable('permissions')) {
            Permission::query()->where([
                'name' => self::CREATE_PERMISSION,
                'guard_name' => 'web',
            ])->delete();
            app(PermissionRegistrar::class)->forgetCachedPermissions();
        }
    }
};
