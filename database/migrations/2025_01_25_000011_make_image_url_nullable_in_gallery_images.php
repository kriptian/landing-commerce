<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('gallery_images', function (Blueprint $table) {
            $table->string('image_url')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('gallery_images', function (Blueprint $table) {
            // Nota: No podemos revertir esto fácilmente si hay registros con null
            // En producción, deberías primero actualizar los nulls a valores por defecto
            $table->string('image_url')->nullable(false)->change();
        });
    }
};

