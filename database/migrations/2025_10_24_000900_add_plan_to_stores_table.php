<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('stores', function (Blueprint $table) {
            if (!Schema::hasColumn('stores', 'plan')) {
                $table->string('plan')->default('emprendedor')->after('custom_domain');
                $table->string('plan_cycle')->nullable()->after('plan'); // mensual | anual (opcional)
                $table->timestamp('plan_started_at')->nullable()->after('plan_cycle');
                $table->timestamp('plan_renews_at')->nullable()->after('plan_started_at');
            }
        });

        // Backfill: todas las tiendas actuales pasan a "negociante"
        try {
            \DB::table('stores')->update(['plan' => 'negociante']);
        } catch (\Throwable $e) {
            // silencioso: en migraciones algunos entornos no tienen tabla aún
        }
    }

    public function down(): void
    {
        Schema::table('stores', function (Blueprint $table) {
            if (Schema::hasColumn('stores', 'plan_renews_at')) $table->dropColumn('plan_renews_at');
            if (Schema::hasColumn('stores', 'plan_started_at')) $table->dropColumn('plan_started_at');
            if (Schema::hasColumn('stores', 'plan_cycle')) $table->dropColumn('plan_cycle');
            if (Schema::hasColumn('stores', 'plan')) $table->dropColumn('plan');
        });
    }
};


