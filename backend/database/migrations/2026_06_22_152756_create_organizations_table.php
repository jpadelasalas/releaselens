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
        Schema::create('organizations', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('timezone', 64)->default('UTC');
            $table->boolean('is_demo')->default(false)->index();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
        Schema::dropIfExists('sync_run_errors');
        Schema::dropIfExists('sync_runs');
        Schema::dropIfExists('pull_request_reviews');
        Schema::dropIfExists('pull_requests');
        Schema::dropIfExists('repositories');
        Schema::dropIfExists('github_installations');
        Schema::dropIfExists('organization_members');
        Schema::dropIfExists('organizations');
    }
};
