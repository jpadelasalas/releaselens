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
        Schema::create('webhook_processing_attempts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('webhook_delivery_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('attempt_number');
            $table->string('status', 32);
            $table->timestampTz('started_at');
            $table->timestampTz('completed_at')->nullable();
            $table->timestampTz('next_retry_at')->nullable()->index();
            $table->string('handler_version', 32)->nullable();
            $table->string('error_category')->nullable();
            $table->text('error_summary')->nullable();
            $table->timestamps();

            $table->unique(['webhook_delivery_id', 'attempt_number']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('webhook_processing_attempts');
    }
};
