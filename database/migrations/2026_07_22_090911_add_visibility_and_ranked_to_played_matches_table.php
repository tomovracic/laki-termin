<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('played_matches', function (Blueprint $table): void {
            $table->boolean('is_public')->default(true)->after('entered_by');
            $table->boolean('is_ranked')->default(true)->after('is_public');
        });
    }

    public function down(): void
    {
        Schema::table('played_matches', function (Blueprint $table): void {
            $table->dropColumn(['is_public', 'is_ranked']);
        });
    }
};
