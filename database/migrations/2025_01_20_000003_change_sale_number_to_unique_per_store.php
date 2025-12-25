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
        Schema::table('physical_sales', function (Blueprint $table) {
            // Eliminar el índice único global de sale_number
            $table->dropUnique(['sale_number']);
            
            // Crear un índice único compuesto por store_id y sale_number
            // Esto permite que cada tienda tenga su propia secuencia de números de venta
            $table->unique(['store_id', 'sale_number'], 'physical_sales_store_sale_number_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('physical_sales', function (Blueprint $table) {
            // Eliminar el índice compuesto
            $table->dropUnique('physical_sales_store_sale_number_unique');
            
            // Restaurar el índice único global
            $table->unique('sale_number');
        });
    }
};

