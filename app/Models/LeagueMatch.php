<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\LeagueMatchStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LeagueMatch extends Model
{
    /** @use HasFactory<\Database\Factories\LeagueMatchFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'league_id',
        'player_one_id',
        'player_one_first_name',
        'player_one_last_name',
        'player_two_id',
        'player_two_first_name',
        'player_two_last_name',
        'round',
        'bracket_round',
        'bracket_position',
        'next_match_id',
        'next_match_slot',
        'is_bye',
        'status',
        'set1_player_one_games',
        'set1_player_two_games',
        'set2_player_one_games',
        'set2_player_two_games',
        'set3_player_one_games',
        'set3_player_two_games',
        'set4_player_one_games',
        'set4_player_two_games',
        'set5_player_one_games',
        'set5_player_two_games',
        'played_at',
        'entered_by',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => LeagueMatchStatus::class,
            'played_at' => 'immutable_datetime',
            'is_bye' => 'boolean',
            'bracket_round' => 'integer',
            'bracket_position' => 'integer',
            'next_match_slot' => 'integer',
        ];
    }

    public function league(): BelongsTo
    {
        return $this->belongsTo(League::class);
    }

    public function playerOne(): BelongsTo
    {
        return $this->belongsTo(User::class, 'player_one_id');
    }

    public function playerTwo(): BelongsTo
    {
        return $this->belongsTo(User::class, 'player_two_id');
    }

    public function nextMatch(): BelongsTo
    {
        return $this->belongsTo(self::class, 'next_match_id');
    }

    public function enteredBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'entered_by');
    }

    public function playerOneDisplayName(): string
    {
        if ($this->playerOne !== null) {
            return $this->playerOne->name;
        }

        return trim(($this->player_one_first_name ?? '').' '.($this->player_one_last_name ?? ''));
    }

    public function playerTwoDisplayName(): string
    {
        if ($this->playerTwo !== null) {
            return $this->playerTwo->name;
        }

        return trim(($this->player_two_first_name ?? '').' '.($this->player_two_last_name ?? ''));
    }

    public function hasPlayerOne(): bool
    {
        return $this->player_one_id !== null
            || ($this->player_one_first_name !== null && $this->player_one_last_name !== null);
    }

    public function hasPlayerTwo(): bool
    {
        return $this->player_two_id !== null
            || ($this->player_two_first_name !== null && $this->player_two_last_name !== null);
    }

    /**
     * Empty first-round bracket slot (both sides vacant), not a player bye.
     */
    public function isEmptyBracketSlot(): bool
    {
        return ! $this->is_bye
            && ! $this->hasPlayerOne()
            && ! $this->hasPlayerTwo()
            && $this->status === LeagueMatchStatus::Played;
    }

    /**
     * @return array{user_id: int|null, first_name: string|null, last_name: string|null}
     */
    public function playerOneIdentity(): array
    {
        return [
            'user_id' => $this->player_one_id,
            'first_name' => $this->player_one_id === null ? $this->player_one_first_name : null,
            'last_name' => $this->player_one_id === null ? $this->player_one_last_name : null,
        ];
    }

    /**
     * @return array{user_id: int|null, first_name: string|null, last_name: string|null}
     */
    public function playerTwoIdentity(): array
    {
        return [
            'user_id' => $this->player_two_id,
            'first_name' => $this->player_two_id === null ? $this->player_two_first_name : null,
            'last_name' => $this->player_two_id === null ? $this->player_two_last_name : null,
        ];
    }

    public function scopePlayed(Builder $query): Builder
    {
        return $query->where('status', LeagueMatchStatus::Played->value);
    }

    public function scopePending(Builder $query): Builder
    {
        return $query->where('status', LeagueMatchStatus::Pending->value);
    }

    public function scopeForUser(Builder $query, int $userId): Builder
    {
        return $query->where(function (Builder $inner) use ($userId): void {
            $inner->where('player_one_id', $userId)
                ->orWhere('player_two_id', $userId);
        });
    }
}
