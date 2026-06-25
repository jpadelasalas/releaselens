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
        Schema::create('pull_request_reviews', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pull_request_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('github_review_id')->unique();
            $table->foreignId('reviewer_github_user_id')->nullable()->constrained('github_users')->nullOnDelete();
            $table->string('state', 32)->index();
            $table->timestampTz('submitted_at')->nullable()->index();
            $table->timestampTz('github_updated_at')->nullable();
            $table->timestamps();

            $table->index(['pull_request_id', 'submitted_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('pull_request_reviews');
    }
};
