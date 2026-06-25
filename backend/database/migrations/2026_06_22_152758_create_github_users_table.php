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
        Schema::create('github_users', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('github_user_id')->unique();
            $table->string('login')->index();
            $table->string('name')->nullable();
            $table->string('display_name')->nullable();
            $table->string('type', 32);
            $table->string('account_type', 32)->nullable();
            $table->boolean('is_bot')->default(false)->index();
            $table->string('avatar_url', 2048)->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('github_users');
    }
};
