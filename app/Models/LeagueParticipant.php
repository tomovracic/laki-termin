<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LeagueParticipant extends Model
{
    /** @use HasFactory<\Database\Factories\LeagueParticipantFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'league_id',
        'user_id',
        'first_name',
        'last_name',
        'seed',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'seed' => 'integer',
        ];
    }

    public function league(): BelongsTo
    {
        return $this->belongsTo(League::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function displayName(): string
    {
        if ($this->user !== null) {
            return $this->user->name;
        }

        return trim(($this->first_name ?? '').' '.($this->last_name ?? ''));
    }

    public function isGuest(): bool
    {
        return $this->user_id === null;
    }
}
