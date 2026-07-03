<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('leagues', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->unsignedTinyInteger('rounds');
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();
        });

        Schema::create('league_participants', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('league_id')->constrained('leagues')->restrictOnDelete();
            $table->foreignId('user_id')->constrained('users')->restrictOnDelete();
            $table->timestamps();

            $table->unique(['league_id', 'user_id']);
        });

        Schema::create('league_matches', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('league_id')->constrained('leagues')->restrictOnDelete();
            $table->foreignId('player_one_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('player_two_id')->constrained('users')->restrictOnDelete();
            $table->unsignedTinyInteger('round');
            $table->string('status', 32);
            $table->unsignedSmallInteger('set1_player_one_games')->nullable();
            $table->unsignedSmallInteger('set1_player_two_games')->nullable();
            $table->unsignedSmallInteger('set2_player_one_games')->nullable();
            $table->unsignedSmallInteger('set2_player_two_games')->nullable();
            $table->unsignedSmallInteger('set3_player_one_games')->nullable();
            $table->unsignedSmallInteger('set3_player_two_games')->nullable();
            $table->timestamp('played_at')->nullable();
            $table->foreignId('entered_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['league_id', 'player_one_id', 'player_two_id', 'round'], 'league_match_pair_round_unique');
            $table->index(['league_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('league_matches');
        Schema::dropIfExists('league_participants');
        Schema::dropIfExists('leagues');
    }
};
