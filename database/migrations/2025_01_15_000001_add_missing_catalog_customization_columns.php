<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Primero verificar qué columnas existen
        $hasPromoBanner = Schema::hasColumn('stores', 'catalog_promo_banner_color');
        $hasVariant = Schema::hasColumn('stores', 'catalog_variant_button_color');
        $hasPurchase = Schema::hasColumn('stores', 'catalog_purchase_button_color');
        $hasCart = Schema::hasColumn('stores', 'catalog_cart_bubble_color');
        $hasSocial = Schema::hasColumn('stores', 'catalog_social_button_color');
        $hasLogo = Schema::hasColumn('stores', 'catalog_logo_position');
        
        Schema::table('stores', function (Blueprint $table) use ($hasPromoBanner, $hasVariant, $hasPurchase, $hasCart, $hasSocial, $hasLogo) {
            // Determinar la columna de referencia para la primera columna nueva
            $ref = $hasPromoBanner ? 'catalog_promo_banner_color' : 'plan_renews_at';
            
            if (!$hasVariant) {
                $table->string('catalog_variant_button_color')->nullable()->after($ref);
                $ref = 'catalog_variant_button_color';
            } elseif ($hasVariant) {
                $ref = 'catalog_variant_button_color';
            }
            
            if (!$hasPurchase) {
                $table->string('catalog_purchase_button_color')->nullable()->after($ref);
                $ref = 'catalog_purchase_button_color';
            } elseif ($hasPurchase) {
                $ref = 'catalog_purchase_button_color';
            }
            
            if (!$hasCart) {
                $table->string('catalog_cart_bubble_color')->nullable()->after($ref);
                $ref = 'catalog_cart_bubble_color';
            } elseif ($hasCart) {
                $ref = 'catalog_cart_bubble_color';
            }
            
            if (!$hasSocial) {
                $table->string('catalog_social_button_color')->nullable()->after($ref);
                $ref = 'catalog_social_button_color';
            } elseif ($hasSocial) {
                $ref = 'catalog_social_button_color';
            }
            
            if (!$hasLogo) {
                $table->string('catalog_logo_position')->default('center')->after($ref);
                $ref = 'catalog_logo_position';
            } elseif ($hasLogo) {
                $ref = 'catalog_logo_position';
            }
            
            if (!Schema::hasColumn('stores', 'catalog_menu_type')) {
                $table->string('catalog_menu_type')->default('hamburger')->after($ref);
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
        });
    }
};
