<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('stores', function (Blueprint $table) {
            $table->enum('gallery_type', ['products', 'custom'])->default('products')->after('catalog_promo_banner_text_color');
            $table->boolean('gallery_show_buy_button')->default(true)->after('gallery_type');
        });
    }

    public function down(): void
    {
        Schema::table('stores', function (Blueprint $table) {
            $table->dropColumn(['gallery_type', 'gallery_show_buy_button']);
        });
    }
};

