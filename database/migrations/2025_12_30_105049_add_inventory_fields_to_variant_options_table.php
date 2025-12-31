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
        Schema::table('variant_options', function (Blueprint $table) {
            $table->integer('stock')->default(0)->after('price');
            $table->integer('alert')->nullable()->after('stock');
            $table->decimal('purchase_price', 10, 2)->nullable()->after('alert');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('variant_options', function (Blueprint $table) {
            $table->dropColumn(['stock', 'alert', 'purchase_price']);
        });
    }
};
