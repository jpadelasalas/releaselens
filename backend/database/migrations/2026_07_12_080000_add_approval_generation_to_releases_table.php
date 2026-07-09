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
        Schema::table('releases', function (Blueprint $table): void {
            $table->unsignedInteger('approval_generation')->default(0);
        });

        Schema::table('release_approvals', function (Blueprint $table): void {
            $table->unsignedInteger('approval_generation')->default(0);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('releases', function (Blueprint $table): void {
            $table->dropColumn('approval_generation');
        });

        Schema::table('release_approvals', function (Blueprint $table): void {
            $table->dropColumn('approval_generation');
        });
    }
};
