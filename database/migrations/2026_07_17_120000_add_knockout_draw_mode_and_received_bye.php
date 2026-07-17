<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('leagues', function (Blueprint $table): void {
            $table->string('knockout_draw_mode', 32)->nullable()->after('sets_best_of');
        });

        Schema::table('league_participants', function (Blueprint $table): void {
            $table->boolean('received_bye')->default(false)->after('seed');
        });
    }

    public function down(): void
    {
        Schema::table('leagues', function (Blueprint $table): void {
            $table->dropColumn('knockout_draw_mode');
        });

        Schema::table('league_participants', function (Blueprint $table): void {
            $table->dropColumn('received_bye');
        });
    }
};
