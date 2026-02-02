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
            $table->decimal('delivery_cost', 10, 2)->default(0)->after('promo_discount_percent');
            $table->boolean('delivery_cost_active')->default(false)->after('delivery_cost');
        });

        Schema::table('physical_sales', function (Blueprint $table) {
            $table->decimal('delivery_cost', 10, 2)->default(0)->after('discount');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('stores', function (Blueprint $table) {
            $table->dropColumn(['delivery_cost', 'delivery_cost_active']);
        });

        Schema::table('physical_sales', function (Blueprint $table) {
            $table->dropColumn('delivery_cost');
        });
    }
};
