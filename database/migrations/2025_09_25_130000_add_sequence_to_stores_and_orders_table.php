<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('stores', function (Blueprint $table) {
            if (!Schema::hasColumn('stores', 'order_sequence')) {
                $table->unsignedBigInteger('order_sequence')->default(0)->after('max_users');
            }
        });

        Schema::table('orders', function (Blueprint $table) {
            if (!Schema::hasColumn('orders', 'sequence_number')) {
                $table->unsignedBigInteger('sequence_number')->nullable()->after('id');
                $table->index(['store_id', 'sequence_number']);
            }
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            if (Schema::hasColumn('orders', 'sequence_number')) {
                $table->dropIndex(['store_id', 'sequence_number']);
                $table->dropColumn('sequence_number');
            }
        });

        Schema::table('stores', function (Blueprint $table) {
            if (Schema::hasColumn('stores', 'order_sequence')) {
                $table->dropColumn('order_sequence');
            }
        });
    }
};


