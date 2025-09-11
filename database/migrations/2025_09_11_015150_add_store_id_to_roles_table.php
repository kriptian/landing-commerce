<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('roles', function (Blueprint $table) {
            // Primero, quitamos la regla de unicidad vieja que solo miraba el nombre.
            $table->dropUnique('roles_name_guard_name_unique');

            // Añadimos la columna para la tienda
            $table->foreignId('store_id')->nullable()->constrained('stores')->after('name');

            // AHORA, creamos la nueva regla de unicidad que incluye la tienda
            $table->unique(['name', 'guard_name', 'store_id']);
        });
    }

    public function down(): void
    {
        Schema::table('roles', function (Blueprint $table) {
            $table->dropForeign(['store_id']);
            $table->dropUnique(['name', 'guard_name', 'store_id']);
            $table->dropColumn('store_id');
            $table->unique(['name', 'guard_name']);
        });
    }
};