<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('league_matches', function (Blueprint $table): void {
            $table->unsignedInteger('schedule_order')->nullable()->after('is_bye');
            $table->index(['league_id', 'schedule_order']);
        });
    }

    public function down(): void
    {
        Schema::table('league_matches', function (Blueprint $table): void {
            $table->dropIndex(['league_id', 'schedule_order']);
            $table->dropColumn('schedule_order');
        });
    }
};
