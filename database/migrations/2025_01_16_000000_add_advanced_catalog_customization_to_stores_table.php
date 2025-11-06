<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('stores', function (Blueprint $table) {
            // Plantilla de productos
            if (!Schema::hasColumn('stores', 'catalog_product_template')) {
                $table->string('catalog_product_template')->default('default')->after('catalog_menu_type');
            }
            
            // Estilo de header
            if (!Schema::hasColumn('stores', 'catalog_header_style')) {
                $table->string('catalog_header_style')->default('default')->after('catalog_product_template');
            }
            
            // Colores granulares del header
            if (!Schema::hasColumn('stores', 'catalog_header_bg_color')) {
                $table->string('catalog_header_bg_color')->nullable()->after('catalog_header_style');
                $table->string('catalog_header_text_color')->nullable()->after('catalog_header_bg_color');
            }
            
            // Colores granulares de botones (separar fondo y texto)
            if (!Schema::hasColumn('stores', 'catalog_button_bg_color')) {
                $table->string('catalog_button_bg_color')->nullable()->after('catalog_header_text_color');
                $table->string('catalog_button_text_color')->nullable()->after('catalog_button_bg_color');
            }
            
            // Colores granulares del body
            if (!Schema::hasColumn('stores', 'catalog_body_bg_color')) {
                $table->string('catalog_body_bg_color')->nullable()->after('catalog_button_text_color');
                $table->string('catalog_body_text_color')->nullable()->after('catalog_body_bg_color');
            }
            
            // Colores granulares de inputs
            if (!Schema::hasColumn('stores', 'catalog_input_bg_color')) {
                $table->string('catalog_input_bg_color')->nullable()->after('catalog_body_text_color');
                $table->string('catalog_input_text_color')->nullable()->after('catalog_input_bg_color');
            }
        });
    }

    public function down(): void
    {
        Schema::table('stores', function (Blueprint $table) {
            if (Schema::hasColumn('stores', 'catalog_input_text_color')) {
                $table->dropColumn('catalog_input_text_color');
            }
            if (Schema::hasColumn('stores', 'catalog_input_bg_color')) {
                $table->dropColumn('catalog_input_bg_color');
            }
            if (Schema::hasColumn('stores', 'catalog_body_text_color')) {
                $table->dropColumn('catalog_body_text_color');
            }
            if (Schema::hasColumn('stores', 'catalog_body_bg_color')) {
                $table->dropColumn('catalog_body_bg_color');
            }
            if (Schema::hasColumn('stores', 'catalog_button_text_color')) {
                $table->dropColumn('catalog_button_text_color');
            }
            if (Schema::hasColumn('stores', 'catalog_button_bg_color')) {
                $table->dropColumn('catalog_button_bg_color');
            }
            if (Schema::hasColumn('stores', 'catalog_header_text_color')) {
                $table->dropColumn('catalog_header_text_color');
            }
            if (Schema::hasColumn('stores', 'catalog_header_bg_color')) {
                $table->dropColumn('catalog_header_bg_color');
            }
            if (Schema::hasColumn('stores', 'catalog_header_style')) {
                $table->dropColumn('catalog_header_style');
            }
            if (Schema::hasColumn('stores', 'catalog_product_template')) {
                $table->dropColumn('catalog_product_template');
            }
        });
    }
};

