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
            $table->boolean('popup_active')->default(false);
            $table->string('popup_image_path')->nullable();
            $table->string('popup_button_text')->nullable();
            $table->string('popup_button_link')->nullable();
            $table->boolean('popup_show_button')->default(true);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('stores', function (Blueprint $table) {
            $table->dropColumn([
                'popup_active',
                'popup_image_path',
                'popup_button_text',
                'popup_button_link',
                'popup_show_button',
            ]);
        });
    }
};
