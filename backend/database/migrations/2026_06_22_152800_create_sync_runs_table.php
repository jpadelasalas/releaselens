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
        Schema::create('sync_runs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('repository_id')->constrained()->cascadeOnDelete();
            $table->string('trigger_type', 32);
            $table->string('status', 32)->index();
            $table->timestampTz('started_at')->nullable()->index();
            $table->timestampTz('completed_at')->nullable();
            $table->text('cursor_before')->nullable();
            $table->text('cursor_after')->nullable();
            $table->unsignedInteger('created_count')->default(0);
            $table->unsignedInteger('updated_count')->default(0);
            $table->unsignedInteger('unchanged_count')->default(0);
            $table->unsignedInteger('skipped_count')->default(0);
            $table->unsignedInteger('failed_count')->default(0);
            $table->unsignedInteger('rate_limit_remaining')->nullable();
            $table->timestampTz('rate_limit_reset_at')->nullable();
            $table->string('error_category')->nullable();
            $table->text('error_summary')->nullable();
            $table->timestamps();

            $table->index(['repository_id', 'status']);
            $table->index(['repository_id', 'started_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sync_runs');
    }
};
