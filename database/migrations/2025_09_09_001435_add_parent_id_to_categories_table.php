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
    Schema::table('categories', function (Blueprint $table) {
        // Creamos la columna para el ID del padre.
        // Es "nullable" para que las categorías principales no necesiten un padre.
        // Apunta a la misma tabla "categories".
        $table->foreignId('parent_id')
              ->nullable()
              ->constrained('categories')
              ->onDelete('cascade'); // Ojo: si borras un papá, se borran los hijos.
    });
}

public function down(): void
{
    Schema::table('categories', function (Blueprint $table) {
        // Esto es para poder deshacer el cambio si es necesario
        $table->dropForeign(['parent_id']);
        $table->dropColumn('parent_id');
    });
}
};
