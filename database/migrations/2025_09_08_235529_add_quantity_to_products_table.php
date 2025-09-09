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
        Schema::table('products', function (Blueprint $table) {
            // Le decimos que agregue una columna para números enteros llamada 'quantity',
            // que por defecto sea 0 y que la ponga después de la columna 'price'.
            $table->integer('quantity')->default(0)->after('price');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            // Esto es por si alguna vez necesitamos deshacer el cambio
            $table->dropColumn('quantity');
        });
    }
};
