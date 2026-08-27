<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('leagues', function (Blueprint $table): void {
            $table->unsignedTinyInteger('qualify_per_group')->nullable()->after('knockout_draw_mode');
            $table->unsignedTinyInteger('best_runners_up')->nullable()->after('qualify_per_group');
            $table->string('current_stage', 16)->nullable()->after('best_runners_up');
        });

        Schema::create('league_groups', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('league_id')->constrained('leagues')->restrictOnDelete();
            $table->string('name');
            $table->unsignedTinyInteger('sort_order');
            $table->timestamps();

            $table->unique(['league_id', 'sort_order']);
        });

        Schema::table('league_participants', function (Blueprint $table): void {
            $table->foreignId('league_group_id')
                ->nullable()
                ->after('league_id')
                ->constrained('league_groups')
                ->nullOnDelete();
        });

        Schema::table('league_matches', function (Blueprint $table): void {
            $table->foreignId('league_group_id')
                ->nullable()
                ->after('league_id')
                ->constrained('league_groups')
                ->nullOnDelete();
            $table->foreignId('player_one_participant_id')
                ->nullable()
                ->after('player_one_partner_id')
                ->constrained('league_participants')
                ->restrictOnDelete();
            $table->foreignId('player_two_participant_id')
                ->nullable()
                ->after('player_two_partner_id')
                ->constrained('league_participants')
                ->restrictOnDelete();
        });

        $this->backfillParticipantIds('player_one_id', 'player_one_participant_id');
        $this->backfillParticipantIds('player_two_id', 'player_two_participant_id');
    }

    public function down(): void
    {
        Schema::table('league_matches', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('player_one_participant_id');
            $table->dropConstrainedForeignId('player_two_participant_id');
            $table->dropConstrainedForeignId('league_group_id');
        });

        Schema::table('league_participants', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('league_group_id');
        });

        Schema::dropIfExists('league_groups');

        Schema::table('leagues', function (Blueprint $table): void {
            $table->dropColumn(['qualify_per_group', 'best_runners_up', 'current_stage']);
        });
    }

    private function backfillParticipantIds(string $userColumn, string $participantColumn): void
    {
        $matches = DB::table('league_matches')
            ->whereNotNull($userColumn)
            ->get(['id', 'league_id', $userColumn]);

        foreach ($matches as $match) {
            $participantId = DB::table('league_participants')
                ->where('league_id', $match->league_id)
                ->where('user_id', $match->{$userColumn})
                ->value('id');

            if ($participantId === null) {
                continue;
            }

            DB::table('league_matches')
                ->where('id', $match->id)
                ->update([$participantColumn => $participantId]);
        }
    }
};
