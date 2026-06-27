<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('repositories', function (Blueprint $table): void {
            $table->dropUnique(['github_repository_id']);
            $table->unique(['organization_id', 'github_repository_id']);
        });
    }

    public function down(): void
    {
        Schema::table('repositories', function (Blueprint $table): void {
            $table->dropUnique(['organization_id', 'github_repository_id']);
            $table->unique('github_repository_id');
        });
    }
};
