<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('leagues', function (Blueprint $table): void {
            $table->string('participant_mode', 16)->default('singles')->after('format');
        });

        Schema::table('league_participants', function (Blueprint $table): void {
            $table->foreignId('partner_user_id')
                ->nullable()
                ->after('user_id')
                ->constrained('users')
                ->restrictOnDelete();
        });

        Schema::table('league_matches', function (Blueprint $table): void {
            $table->unsignedBigInteger('player_one_partner_id')->nullable()->after('player_one_last_name');
            $table->unsignedBigInteger('player_two_partner_id')->nullable()->after('player_two_last_name');
            $table->foreign('player_one_partner_id')->references('id')->on('users')->restrictOnDelete();
            $table->foreign('player_two_partner_id')->references('id')->on('users')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('league_matches', function (Blueprint $table): void {
            $table->dropForeign(['player_one_partner_id']);
            $table->dropForeign(['player_two_partner_id']);
            $table->dropColumn(['player_one_partner_id', 'player_two_partner_id']);
        });

        Schema::table('league_participants', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('partner_user_id');
        });

        Schema::table('leagues', function (Blueprint $table): void {
            $table->dropColumn('participant_mode');
        });
    }
};
