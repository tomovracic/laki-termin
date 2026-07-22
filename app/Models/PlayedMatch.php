<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PlayedMatch extends Model
{
    /** @use HasFactory<\Database\Factories\PlayedMatchFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'player_one_user_id',
        'player_one_first_name',
        'player_one_last_name',
        'player_two_user_id',
        'player_two_first_name',
        'player_two_last_name',
        'set1_player_one_games',
        'set1_player_two_games',
        'set2_player_one_games',
        'set2_player_two_games',
        'set3_player_one_games',
        'set3_player_two_games',
        'played_at',
        'entered_by',
        'is_public',
        'is_ranked',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'played_at' => 'immutable_datetime',
            'is_public' => 'boolean',
            'is_ranked' => 'boolean',
        ];
    }

    public function playerOne(): BelongsTo
    {
        return $this->belongsTo(User::class, 'player_one_user_id');
    }

    public function playerTwo(): BelongsTo
    {
        return $this->belongsTo(User::class, 'player_two_user_id');
    }

    public function enteredBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'entered_by');
    }

    public function scopeForUser(Builder $query, int $userId): Builder
    {
        return $query->where(function (Builder $inner) use ($userId): void {
            $inner->where('player_one_user_id', $userId)
                ->orWhere('player_two_user_id', $userId);
        });
    }

    public function playerOneDisplayName(): string
    {
        if ($this->player_one_user_id !== null && $this->relationLoaded('playerOne') && $this->playerOne !== null) {
            return $this->playerOne->name;
        }

        return trim("{$this->player_one_first_name} {$this->player_one_last_name}");
    }

    public function playerTwoDisplayName(): string
    {
        if ($this->player_two_user_id !== null && $this->relationLoaded('playerTwo') && $this->playerTwo !== null) {
            return $this->playerTwo->name;
        }

        return trim("{$this->player_two_first_name} {$this->player_two_last_name}");
    }
}
