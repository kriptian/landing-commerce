<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Dropeamos el índice global si existe, vía SQL directo para evitar nombres inconsistentes
        $exists = DB::select("SHOW INDEX FROM roles WHERE Key_name = 'roles_name_guard_name_unique'");
        if (!empty($exists)) {
            DB::statement('ALTER TABLE roles DROP INDEX roles_name_guard_name_unique');
        }
        // Si aún existiera un índice único equivalente, lo intentamos también por columnas
        $byCols = DB::select("SHOW INDEX FROM roles WHERE Column_name IN ('name','guard_name') AND Non_unique = 0");
        if (!empty($byCols) && empty($exists)) {
            // No sabemos el nombre; intentamos dropear por SQL generando nombres comunes
            try { DB::statement('ALTER TABLE roles DROP INDEX roles_name_unique'); } catch (\Throwable $e) {}
            try { DB::statement('ALTER TABLE roles DROP INDEX roles_guard_name_unique'); } catch (\Throwable $e) {}
        }

        // Creamos el índice único por tienda si no existe
        $scoped = DB::select("SHOW INDEX FROM roles WHERE Key_name = 'roles_store_name_guard_unique'");
        if (empty($scoped)) {
            DB::statement('ALTER TABLE roles ADD UNIQUE roles_store_name_guard_unique (store_id, name, guard_name)');
        }
    }

    public function down(): void
    {
        $scoped = DB::select("SHOW INDEX FROM roles WHERE Key_name = 'roles_store_name_guard_unique'");
        if (!empty($scoped)) {
            DB::statement('ALTER TABLE roles DROP INDEX roles_store_name_guard_unique');
        }
        $global = DB::select("SHOW INDEX FROM roles WHERE Key_name = 'roles_name_guard_name_unique'");
        if (empty($global)) {
            DB::statement('ALTER TABLE roles ADD UNIQUE roles_name_guard_name_unique (name, guard_name)');
        }
    }
};


