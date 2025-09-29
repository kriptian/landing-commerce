<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            if (!Schema::hasColumn('products', 'is_featured')) {
                $table->boolean('is_featured')->default(false)->after('specifications');
            }
            if (!Schema::hasColumn('products', 'promo_active')) {
                $table->boolean('promo_active')->default(false)->after('is_featured');
            }
            if (!Schema::hasColumn('products', 'promo_discount_percent')) {
                $table->unsignedTinyInteger('promo_discount_percent')->nullable()->after('promo_active');
            }
        });

        Schema::table('stores', function (Blueprint $table) {
            if (!Schema::hasColumn('stores', 'promo_active')) {
                $table->boolean('promo_active')->default(false)->after('custom_domain');
            }
            if (!Schema::hasColumn('stores', 'promo_discount_percent')) {
                $table->unsignedTinyInteger('promo_discount_percent')->nullable()->after('promo_active');
            }
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            if (Schema::hasColumn('products', 'promo_discount_percent')) $table->dropColumn('promo_discount_percent');
            if (Schema::hasColumn('products', 'promo_active')) $table->dropColumn('promo_active');
            if (Schema::hasColumn('products', 'is_featured')) $table->dropColumn('is_featured');
        });

        Schema::table('stores', function (Blueprint $table) {
            if (Schema::hasColumn('stores', 'promo_discount_percent')) $table->dropColumn('promo_discount_percent');
            if (Schema::hasColumn('stores', 'promo_active')) $table->dropColumn('promo_active');
        });
    }
};


