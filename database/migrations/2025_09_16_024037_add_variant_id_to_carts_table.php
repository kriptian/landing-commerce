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
        Schema::table('carts', function (Blueprint $table) {
            // Esta es la nueva columna para guardar QUÉ variante escogieron
            $table->foreignId('product_variant_id')
                ->nullable() // Permite nulos (para productos sin variantes)
                ->after('product_id') // La ponemos después de 'product_id'
                ->constrained('product_variants') // Amarrado a la tabla 'product_variants'
                ->onDelete('cascade'); // Si se borra la variante, se borra del carrito
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('carts', function (Blueprint $table) {
            // Esto borra la columna si hacemos rollback
            $table->dropForeign(['product_variant_id']);
            $table->dropColumn('product_variant_id');
        });
    }
};
