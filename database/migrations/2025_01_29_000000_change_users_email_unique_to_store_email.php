<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Eliminar la restricción única global en email
            $table->dropUnique(['email']);
            
            // Crear una restricción única compuesta: email único por tienda
            // Esto permite que el mismo email exista en diferentes tiendas
            $table->unique(['store_id', 'email'], 'users_store_email_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Eliminar la restricción única compuesta
            $table->dropUnique('users_store_email_unique');
            
            // Restaurar la restricción única global en email
            $table->unique('email');
        });
    }
};

