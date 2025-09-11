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
        Schema::table('stores', function (Blueprint $table) {
            // Añadimos las columnas después de la columna 'user_id'
            $table->string('phone')->nullable()->after('user_id');
            $table->string('address')->nullable()->after('phone');
            $table->string('facebook_url')->nullable()->after('address');
            $table->string('instagram_url')->nullable()->after('facebook_url');
        });
    }

    /**
     * Reverse the migrations.
     */
    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('stores', function (Blueprint $table) {
            // Le decimos que borre las columnas que creamos en el 'up'
            $table->dropColumn(['phone', 'address', 'facebook_url', 'instagram_url']);
        });
    }
};
