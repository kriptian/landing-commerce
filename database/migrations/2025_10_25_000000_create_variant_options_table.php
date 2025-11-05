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
        Schema::create('variant_options', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->onDelete('cascade');
            $table->foreignId('parent_id')->nullable()->constrained('variant_options')->onDelete('cascade');
            $table->string('name'); // Nombre de la variante/opción (ej: "Color", "Verde", "Rojo")
            $table->decimal('price', 10, 2)->nullable(); // Precio opcional para esta opción
            $table->string('image_path')->nullable(); // Ruta de la imagen opcional para esta opción
            $table->integer('order')->default(0); // Para ordenar las opciones
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('variant_options');
    }
};

