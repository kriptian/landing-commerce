<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('stores', function (Blueprint $table) {
            if (!Schema::hasColumn('stores', 'catalog_use_default')) {
                $table->boolean('catalog_use_default')->default(true)->after('plan_renews_at');
                $table->string('catalog_button_color')->nullable()->after('catalog_use_default');
                $table->string('catalog_promo_banner_color')->nullable()->after('catalog_button_color');
                $table->string('catalog_variant_button_color')->nullable()->after('catalog_promo_banner_color');
                $table->string('catalog_purchase_button_color')->nullable()->after('catalog_variant_button_color');
                $table->string('catalog_cart_bubble_color')->nullable()->after('catalog_purchase_button_color');
                $table->string('catalog_social_button_color')->nullable()->after('catalog_cart_bubble_color');
                $table->string('catalog_logo_position')->default('center')->after('catalog_social_button_color'); // left, center, right
                $table->string('catalog_menu_type')->default('hamburger')->after('catalog_logo_position'); // hamburger, dropdown, full
            }
        });
    }

    public function down(): void
    {
        Schema::table('stores', function (Blueprint $table) {
            if (Schema::hasColumn('stores', 'catalog_menu_type')) {
                $table->dropColumn('catalog_menu_type');
            }
            if (Schema::hasColumn('stores', 'catalog_logo_position')) {
                $table->dropColumn('catalog_logo_position');
            }
            if (Schema::hasColumn('stores', 'catalog_social_button_color')) {
                $table->dropColumn('catalog_social_button_color');
            }
            if (Schema::hasColumn('stores', 'catalog_cart_bubble_color')) {
                $table->dropColumn('catalog_cart_bubble_color');
            }
            if (Schema::hasColumn('stores', 'catalog_purchase_button_color')) {
                $table->dropColumn('catalog_purchase_button_color');
            }
            if (Schema::hasColumn('stores', 'catalog_variant_button_color')) {
                $table->dropColumn('catalog_variant_button_color');
            }
            if (Schema::hasColumn('stores', 'catalog_promo_banner_color')) {
                $table->dropColumn('catalog_promo_banner_color');
            }
            if (Schema::hasColumn('stores', 'catalog_button_color')) {
                $table->dropColumn('catalog_button_color');
            }
            if (Schema::hasColumn('stores', 'catalog_use_default')) {
                $table->dropColumn('catalog_use_default');
            }
        });
    }
};

