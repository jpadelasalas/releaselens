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
        Schema::create('deployments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('repository_id')->constrained()->cascadeOnDelete();
            $table->foreignId('release_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedBigInteger('github_deployment_id')->unique();
            $table->string('ref');
            $table->string('sha', 64);
            $table->string('original_environment');
            $table->string('normalized_environment', 32);
            $table->boolean('is_production')->default(false);
            $table->string('status', 32)->default('pending')->index();
            $table->string('original_status', 32)->default('pending');
            $table->text('description')->nullable();
            $table->timestampTz('created_at_github');
            $table->timestampTz('updated_at_github')->nullable();
            $table->timestamps();

            $table->index(['repository_id', 'status']);
            $table->index(['organization_id', 'is_production']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('deployments');
    }
};
