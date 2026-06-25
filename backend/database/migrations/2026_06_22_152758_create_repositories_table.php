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
        Schema::create('repositories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('github_installation_id')->nullable()->constrained('github_installations')->nullOnDelete();
            $table->unsignedBigInteger('github_repository_id')->unique();
            $table->string('name');
            $table->string('full_name')->index();
            $table->text('description')->nullable();
            $table->string('visibility', 32);
            $table->string('default_branch')->nullable();
            $table->string('html_url', 2048)->nullable();
            $table->boolean('is_archived')->default(false);
            $table->boolean('sync_enabled')->default(false)->index();
            $table->string('sync_status', 32)->default('never_synced')->index();
            $table->timestampTz('last_sync_at')->nullable();
            $table->timestampTz('last_successful_sync_at')->nullable();
            $table->timestamps();

            $table->index(['organization_id', 'sync_enabled']);
            $table->index(['organization_id', 'sync_status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('repositories');
    }
};
