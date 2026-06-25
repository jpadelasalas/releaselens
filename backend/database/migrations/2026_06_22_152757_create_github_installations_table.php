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
        Schema::create('github_installations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('github_installation_id')->unique();
            $table->unsignedBigInteger('github_account_id')->nullable()->index();
            $table->string('github_account_login')->nullable();
            $table->string('github_account_type', 32)->nullable();
            $table->string('repository_selection', 32)->nullable();
            $table->json('permissions')->nullable();
            $table->timestampTz('connected_at')->nullable();
            $table->timestampTz('suspended_at')->nullable();
            $table->timestampTz('disconnected_at')->nullable();
            $table->timestamps();

            $table->index(['organization_id', 'disconnected_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('github_installations');
    }
};
