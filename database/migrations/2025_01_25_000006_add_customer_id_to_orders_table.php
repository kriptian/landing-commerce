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
        Schema::table('orders', function (Blueprint $table) {
            $table->foreignId('customer_id')->nullable()->after('store_id')->constrained()->onDelete('set null');
            $table->foreignId('address_id')->nullable()->after('customer_id')->constrained()->onDelete('set null');
            $table->foreignId('coupon_id')->nullable()->after('address_id')->constrained()->onDelete('set null');
            $table->decimal('discount_amount', 10, 2)->default(0)->after('total_price');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropForeign(['customer_id']);
            $table->dropForeign(['address_id']);
            $table->dropForeign(['coupon_id']);
            $table->dropColumn(['customer_id', 'address_id', 'coupon_id', 'discount_amount']);
        });
    }
};

