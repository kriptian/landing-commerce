<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('stores', function (Blueprint $table) {
            if (!Schema::hasColumn('stores', 'catalog_promo_banner_text_color')) {
                $table->string('catalog_promo_banner_text_color')->nullable()->after('catalog_promo_banner_color');
            }
        });
    }

    public function down(): void
    {
        Schema::table('stores', function (Blueprint $table) {
            if (Schema::hasColumn('stores', 'catalog_promo_banner_text_color')) {
                $table->dropColumn('catalog_promo_banner_text_color');
            }
        });
    }
};

