<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('product_variants', function (Blueprint $table) {
            if (! Schema::hasColumn('product_variants', 'purchase_price')) {
                $table->decimal('purchase_price', 12, 2)->nullable()->after('price');
            }
            if (! Schema::hasColumn('product_variants', 'wholesale_price')) {
                $table->decimal('wholesale_price', 12, 2)->nullable()->after('purchase_price');
            }
            if (! Schema::hasColumn('product_variants', 'retail_price')) {
                $table->decimal('retail_price', 12, 2)->nullable()->after('wholesale_price');
            }
        });
    }

    public function down(): void
    {
        Schema::table('product_variants', function (Blueprint $table) {
            if (Schema::hasColumn('product_variants', 'retail_price')) {
                $table->dropColumn('retail_price');
            }
            if (Schema::hasColumn('product_variants', 'wholesale_price')) {
                $table->dropColumn('wholesale_price');
            }
            if (Schema::hasColumn('product_variants', 'purchase_price')) {
                $table->dropColumn('purchase_price');
            }
        });
    }
};


