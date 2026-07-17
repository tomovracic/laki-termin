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
            $table->string('format', 32)->default('round_robin')->after('name');
            $table->unsignedTinyInteger('sets_best_of')->default(3)->after('rounds');
        });

        Schema::table('league_participants', function (Blueprint $table): void {
            $table->dropForeign(['user_id']);
        });

        Schema::table('league_participants', function (Blueprint $table): void {
            $table->unsignedBigInteger('user_id')->nullable()->change();
            $table->string('first_name')->nullable()->after('user_id');
            $table->string('last_name')->nullable()->after('first_name');
            $table->unsignedSmallInteger('seed')->nullable()->after('last_name');
            $table->foreign('user_id')->references('id')->on('users')->restrictOnDelete();
        });

        Schema::table('league_matches', function (Blueprint $table): void {
            $table->dropUnique('league_match_pair_round_unique');
            $table->dropForeign(['player_one_id']);
            $table->dropForeign(['player_two_id']);
        });

        Schema::table('league_matches', function (Blueprint $table): void {
            $table->unsignedBigInteger('player_one_id')->nullable()->change();
            $table->unsignedBigInteger('player_two_id')->nullable()->change();
            $table->string('player_one_first_name')->nullable()->after('player_one_id');
            $table->string('player_one_last_name')->nullable()->after('player_one_first_name');
            $table->string('player_two_first_name')->nullable()->after('player_two_id');
            $table->string('player_two_last_name')->nullable()->after('player_two_first_name');
            $table->unsignedTinyInteger('bracket_round')->nullable()->after('round');
            $table->unsignedSmallInteger('bracket_position')->nullable()->after('bracket_round');
            $table->foreignId('next_match_id')->nullable()->after('bracket_position')->constrained('league_matches')->nullOnDelete();
            $table->unsignedTinyInteger('next_match_slot')->nullable()->after('next_match_id');
            $table->boolean('is_bye')->default(false)->after('next_match_slot');
            $table->unsignedSmallInteger('set4_player_one_games')->nullable()->after('set3_player_two_games');
            $table->unsignedSmallInteger('set4_player_two_games')->nullable()->after('set4_player_one_games');
            $table->unsignedSmallInteger('set5_player_one_games')->nullable()->after('set4_player_two_games');
            $table->unsignedSmallInteger('set5_player_two_games')->nullable()->after('set5_player_one_games');
            $table->foreign('player_one_id')->references('id')->on('users')->restrictOnDelete();
            $table->foreign('player_two_id')->references('id')->on('users')->restrictOnDelete();
            $table->unique(['league_id', 'bracket_round', 'bracket_position'], 'league_match_bracket_slot_unique');
        });
    }

    public function down(): void
    {
        Schema::table('league_matches', function (Blueprint $table): void {
            $table->dropUnique('league_match_bracket_slot_unique');
            $table->dropForeign(['next_match_id']);
            $table->dropForeign(['player_one_id']);
            $table->dropForeign(['player_two_id']);
            $table->dropColumn([
                'player_one_first_name',
                'player_one_last_name',
                'player_two_first_name',
                'player_two_last_name',
                'bracket_round',
                'bracket_position',
                'next_match_id',
                'next_match_slot',
                'is_bye',
                'set4_player_one_games',
                'set4_player_two_games',
                'set5_player_one_games',
                'set5_player_two_games',
            ]);
        });

        Schema::table('league_matches', function (Blueprint $table): void {
            $table->unsignedBigInteger('player_one_id')->nullable(false)->change();
            $table->unsignedBigInteger('player_two_id')->nullable(false)->change();
            $table->foreign('player_one_id')->references('id')->on('users')->restrictOnDelete();
            $table->foreign('player_two_id')->references('id')->on('users')->restrictOnDelete();
            $table->unique(['league_id', 'player_one_id', 'player_two_id', 'round'], 'league_match_pair_round_unique');
        });

        Schema::table('league_participants', function (Blueprint $table): void {
            $table->dropForeign(['user_id']);
            $table->dropColumn(['first_name', 'last_name', 'seed']);
        });

        Schema::table('league_participants', function (Blueprint $table): void {
            $table->unsignedBigInteger('user_id')->nullable(false)->change();
            $table->foreign('user_id')->references('id')->on('users')->restrictOnDelete();
        });

        Schema::table('leagues', function (Blueprint $table): void {
            $table->dropColumn(['format', 'sets_best_of']);
        });
    }
};
