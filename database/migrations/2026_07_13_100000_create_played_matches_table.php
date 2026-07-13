<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('played_matches', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('player_one_user_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->string('player_one_first_name')->nullable();
            $table->string('player_one_last_name')->nullable();
            $table->foreignId('player_two_user_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->string('player_two_first_name')->nullable();
            $table->string('player_two_last_name')->nullable();
            $table->unsignedSmallInteger('set1_player_one_games');
            $table->unsignedSmallInteger('set1_player_two_games');
            $table->unsignedSmallInteger('set2_player_one_games');
            $table->unsignedSmallInteger('set2_player_two_games');
            $table->unsignedSmallInteger('set3_player_one_games')->nullable();
            $table->unsignedSmallInteger('set3_player_two_games')->nullable();
            $table->timestamp('played_at');
            $table->foreignId('entered_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();

            $table->index(['player_one_user_id', 'played_at']);
            $table->index(['player_two_user_id', 'played_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('played_matches');
    }
};
