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
        Schema::create('webhook_deliveries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('repository_id')->nullable()->constrained()->nullOnDelete();
            $table->string('github_delivery_id')->unique();
            $table->unsignedBigInteger('github_hook_id')->nullable();
            $table->unsignedBigInteger('github_installation_id')->nullable()->index();
            $table->string('event_name', 64);
            $table->string('action_name', 64)->nullable();
            $table->string('payload_sha256', 64);
            $table->string('payload_storage_mode', 32)->default('metadata_only');
            $table->string('status', 32)->default('received');
            $table->timestampTz('received_at');
            $table->timestampTz('queued_at')->nullable();
            $table->timestampTz('processed_at')->nullable();
            $table->string('error_category')->nullable();
            $table->text('error_summary')->nullable();
            $table->timestamps();

            $table->index('status');
            $table->index('received_at');
            $table->index(['organization_id', 'repository_id']);
            $table->index(['event_name', 'action_name']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('webhook_deliveries');
    }
};
