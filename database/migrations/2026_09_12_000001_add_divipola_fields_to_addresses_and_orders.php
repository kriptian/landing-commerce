<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('addresses', function (Blueprint $table) {
            $table->string('department_code', 2)->nullable()->after('state');
            $table->string('department_name')->nullable()->after('department_code');
            $table->string('municipality_code', 5)->nullable()->after('department_name');
            $table->string('municipality_name')->nullable()->after('municipality_code');
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->string('department_code', 2)->nullable()->after('customer_address');
            $table->string('department_name')->nullable()->after('department_code');
            $table->string('municipality_code', 5)->nullable()->after('department_name');
            $table->string('municipality_name')->nullable()->after('municipality_code');
        });
    }

    public function down(): void
    {
        Schema::table('addresses', function (Blueprint $table) {
            $table->dropColumn(['department_code', 'department_name', 'municipality_code', 'municipality_name']);
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn(['department_code', 'department_name', 'municipality_code', 'municipality_name']);
        });
    }
};
