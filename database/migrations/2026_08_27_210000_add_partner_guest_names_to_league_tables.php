<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('league_participants', function (Blueprint $table): void {
            $table->string('partner_first_name')->nullable()->after('last_name');
            $table->string('partner_last_name')->nullable()->after('partner_first_name');
        });

        Schema::table('league_matches', function (Blueprint $table): void {
            $table->string('player_one_partner_first_name')->nullable()->after('player_one_partner_id');
            $table->string('player_one_partner_last_name')->nullable()->after('player_one_partner_first_name');
            $table->string('player_two_partner_first_name')->nullable()->after('player_two_partner_id');
            $table->string('player_two_partner_last_name')->nullable()->after('player_two_partner_first_name');
        });
    }

    public function down(): void
    {
        Schema::table('league_matches', function (Blueprint $table): void {
            $table->dropColumn([
                'player_one_partner_first_name',
                'player_one_partner_last_name',
                'player_two_partner_first_name',
                'player_two_partner_last_name',
            ]);
        });

        Schema::table('league_participants', function (Blueprint $table): void {
            $table->dropColumn(['partner_first_name', 'partner_last_name']);
        });
    }
};
