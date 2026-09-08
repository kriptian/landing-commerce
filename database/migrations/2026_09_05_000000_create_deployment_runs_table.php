<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('deployment_runs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('store_id')->constrained()->cascadeOnDelete();
            $table->string('status', 24)->index();
            $table->string('phase', 48)->nullable();
            $table->string('commit_message', 160)->nullable();
            $table->string('prepared_fingerprint', 64)->nullable();
            $table->string('commit_sha', 40)->nullable();
            $table->string('previous_sha', 40)->nullable();
            $table->longText('output')->nullable();
            $table->text('error')->nullable();
            $table->integer('exit_code')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('deployment_runs');
    }
};
