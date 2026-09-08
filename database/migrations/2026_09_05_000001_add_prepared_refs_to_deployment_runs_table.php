<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('deployment_runs', function (Blueprint $table) {
            $table->string('prepared_branch', 255)->nullable()->after('prepared_fingerprint');
            $table->string('prepared_head_sha', 40)->nullable()->after('prepared_branch');
            $table->string('prepared_remote_sha', 40)->nullable()->after('prepared_head_sha');
        });
    }

    public function down(): void
    {
        Schema::table('deployment_runs', function (Blueprint $table) {
            $table->dropColumn(['prepared_branch', 'prepared_head_sha', 'prepared_remote_sha']);
        });
    }
};
