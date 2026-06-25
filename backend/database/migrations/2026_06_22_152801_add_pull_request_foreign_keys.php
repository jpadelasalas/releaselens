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
        Schema::table('pull_requests', function (Blueprint $table) {
            $table->foreign('repository_id')
                ->references('id')
                ->on('repositories')
                ->cascadeOnDelete();

            $table->foreign('author_github_user_id')
                ->references('id')
                ->on('github_users')
                ->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('pull_requests', function (Blueprint $table) {
            $table->dropForeign(['repository_id']);
            $table->dropForeign(['author_github_user_id']);
        });
    }
};
