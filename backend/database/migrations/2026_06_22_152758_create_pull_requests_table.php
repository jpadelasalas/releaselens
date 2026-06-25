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
        Schema::create('pull_requests', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('repository_id');
            $table->unsignedBigInteger('github_pull_request_id')->unique();
            $table->unsignedInteger('number');
            $table->string('title');
            $table->string('html_url', 2048)->nullable();
            $table->string('state', 32)->index();
            $table->boolean('is_draft')->default(false)->index();
            $table->unsignedBigInteger('author_github_user_id')->nullable();
            $table->string('base_ref');
            $table->string('head_ref');
            $table->unsignedInteger('additions')->default(0);
            $table->unsignedInteger('deletions')->default(0);
            $table->unsignedInteger('changed_files')->default(0);
            $table->unsignedInteger('commits_count')->default(0);
            $table->unsignedInteger('comments_count')->default(0);
            $table->timestampTz('created_at_github')->index();
            $table->timestampTz('updated_at_github')->nullable()->index();
            $table->timestampTz('closed_at')->nullable();
            $table->timestampTz('merged_at')->nullable()->index();
            $table->timestamps();

            $table->unique(['repository_id', 'number']);
            $table->index(['repository_id', 'state', 'is_draft']);
            $table->index(['repository_id', 'created_at_github']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('pull_requests');
    }
};
