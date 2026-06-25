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
        Schema::create('sync_run_errors', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sync_run_id')->constrained()->cascadeOnDelete();
            $table->string('category')->index();
            $table->text('message');
            $table->json('safe_context')->nullable();
            $table->timestampTz('occurred_at')->nullable()->index();
            $table->timestamps();

            $table->index(['sync_run_id', 'category']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sync_run_errors');
    }
};
