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
        'player_two_id',
        'round',
        'status',
        'set1_player_one_games',
        'set1_player_two_games',
        'set2_player_one_games',
        'set2_player_two_games',
        'set3_player_one_games',
        'set3_player_two_games',
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

    public function enteredBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'entered_by');
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
