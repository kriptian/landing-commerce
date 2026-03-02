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
            $table->boolean('whatsapp_floating_button_active')->default(false)->after('popup_frequency');
            $table->text('whatsapp_floating_button_message')->nullable()->after('whatsapp_floating_button_active');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('stores', function (Blueprint $table) {
            $table->dropColumn(['whatsapp_floating_button_active', 'whatsapp_floating_button_message']);
        });
    }
};
