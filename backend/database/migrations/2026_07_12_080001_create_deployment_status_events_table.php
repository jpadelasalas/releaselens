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
        Schema::create('deployment_status_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('deployment_id')->constrained()->cascadeOnDelete();
            $table->string('status', 32);
            $table->string('original_status', 32);
            $table->text('description')->nullable();
            $table->string('log_url', 2048)->nullable();
            $table->string('environment_url', 2048)->nullable();
            $table->timestampTz('occurred_at')->index();
            $table->timestamps();

            $table->index(['deployment_id', 'occurred_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('deployment_status_events');
    }
};
