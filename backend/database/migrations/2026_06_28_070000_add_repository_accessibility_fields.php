<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('repositories', function (Blueprint $table): void {
            $table->boolean('is_accessible')->default(true)->index();
            $table->string('access_error')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('repositories', function (Blueprint $table): void {
            $table->dropIndex(['is_accessible']);
            $table->dropColumn(['is_accessible', 'access_error']);
        });
    }
};
