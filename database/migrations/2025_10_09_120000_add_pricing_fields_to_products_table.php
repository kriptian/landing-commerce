<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            if (! Schema::hasColumn('products', 'purchase_price')) {
                $table->decimal('purchase_price', 12, 2)->nullable()->after('price');
            }
            if (! Schema::hasColumn('products', 'wholesale_price')) {
                $table->decimal('wholesale_price', 12, 2)->nullable()->after('purchase_price');
            }
            if (! Schema::hasColumn('products', 'retail_price')) {
                $table->decimal('retail_price', 12, 2)->nullable()->after('wholesale_price');
            }
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            if (Schema::hasColumn('products', 'retail_price')) {
                $table->dropColumn('retail_price');
            }
            if (Schema::hasColumn('products', 'wholesale_price')) {
                $table->dropColumn('wholesale_price');
            }
            if (Schema::hasColumn('products', 'purchase_price')) {
                $table->dropColumn('purchase_price');
            }
        });
    }
};


