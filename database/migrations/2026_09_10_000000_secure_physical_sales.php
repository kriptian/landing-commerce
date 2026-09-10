<?php

use App\Models\Role;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    private const OVERRIDE_PERMISSION = 'modificar precios y descuentos pos';

    public function up(): void
    {
        Schema::table('physical_sales', function (Blueprint $table) {
            $table->decimal('amount_tendered', 15, 2)->nullable()->after('payment_method');
            $table->decimal('change_due', 15, 2)->nullable()->after('amount_tendered');
            $table->uuid('idempotency_key')->nullable()->after('sale_number');
            $table->unique(['store_id', 'idempotency_key'], 'physical_sales_store_idempotency_unique');

            $table->dropForeign(['user_id']);
            $table->unsignedBigInteger('user_id')->nullable()->change();
            $table->foreign('user_id')->references('id')->on('users')->nullOnDelete();
        });

        if (Schema::hasTable('permissions') && Schema::hasTable('roles')) {
            $permission = Permission::firstOrCreate([
                'name' => self::OVERRIDE_PERMISSION,
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
        Schema::table('physical_sales', function (Blueprint $table) {
            $table->dropForeign(['user_id']);
            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
            $table->dropUnique('physical_sales_store_idempotency_unique');
            $table->dropColumn(['amount_tendered', 'change_due', 'idempotency_key']);
        });

        if (Schema::hasTable('permissions')) {
            Permission::query()
                ->where('name', self::OVERRIDE_PERMISSION)
                ->where('guard_name', 'web')
                ->delete();

            app(PermissionRegistrar::class)->forgetCachedPermissions();
        }
    }
};
