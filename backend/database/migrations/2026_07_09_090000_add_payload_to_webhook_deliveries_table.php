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
        Schema::table('webhook_deliveries', function (Blueprint $table): void {
            $table->json('payload')->nullable()->after('payload_storage_mode');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('webhook_deliveries', function (Blueprint $table): void {
            $table->dropColumn('payload');
        });
    }
};
