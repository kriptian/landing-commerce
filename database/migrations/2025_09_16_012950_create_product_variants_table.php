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
        Schema::create('product_variants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->onDelete('cascade');
            $table->json('options'); // <-- Aquí guardamos la combinación: {'Color': 'Rojo', 'Talla': 'M'}
            $table->decimal('price', 10, 2)->nullable(); // Precio de esta combinación (opcional)
            $table->integer('stock')->default(0); // <-- Stock de ESTA COMBINACIÓN
            $table->string('sku')->nullable()->unique(); // SKU para esta combinación (opcional)
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('product_variants');
    }
};
