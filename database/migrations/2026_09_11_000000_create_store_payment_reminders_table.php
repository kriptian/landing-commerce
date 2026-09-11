<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('store_payment_reminders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('store_id')->unique()->constrained()->cascadeOnDelete();
            $table->text('message');
            $table->string('image_path')->nullable();
            $table->string('mode')->default('repeatable');
            $table->unsignedInteger('repeat_interval')->nullable();
            $table->string('repeat_unit')->nullable();
            $table->boolean('pause_catalog')->default(false);
            $table->boolean('active')->default(true);
            $table->timestamp('activated_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('store_payment_reminders');
    }
};
