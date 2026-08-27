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
        'league_group_id',
        'user_id',
        'partner_user_id',
        'first_name',
        'last_name',
        'partner_first_name',
        'partner_last_name',
        'seed',
        'received_bye',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'seed' => 'integer',
            'received_bye' => 'boolean',
        ];
    }

    public function league(): BelongsTo
    {
        return $this->belongsTo(League::class);
    }

    public function group(): BelongsTo
    {
        return $this->belongsTo(LeagueGroup::class, 'league_group_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function partner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'partner_user_id');
    }

    public function displayName(): string
    {
        $primary = $this->user !== null
            ? $this->user->name
            : trim(($this->first_name ?? '').' '.($this->last_name ?? ''));

        $partnerName = $this->partnerDisplayName();

        if ($partnerName !== '') {
            return $primary === '' ? $partnerName : $primary.' / '.$partnerName;
        }

        return $primary;
    }

    public function partnerDisplayName(): string
    {
        if ($this->partner !== null) {
            return $this->partner->name;
        }

        return trim(($this->partner_first_name ?? '').' '.($this->partner_last_name ?? ''));
    }

    public function isGuest(): bool
    {
        return $this->user_id === null;
    }

    /**
     * @return array<string, mixed>
     */
    public function matchSlotAttributes(int $slot): array
    {
        $prefix = $slot === 1 ? 'player_one' : 'player_two';
        $partnerIsGuest = $this->partner_user_id === null;

        return [
            "{$prefix}_id" => $this->user_id,
            "{$prefix}_first_name" => $this->user_id === null ? $this->first_name : null,
            "{$prefix}_last_name" => $this->user_id === null ? $this->last_name : null,
            "{$prefix}_partner_id" => $this->partner_user_id,
            "{$prefix}_partner_first_name" => $partnerIsGuest ? $this->partner_first_name : null,
            "{$prefix}_partner_last_name" => $partnerIsGuest ? $this->partner_last_name : null,
            "{$prefix}_participant_id" => $this->id,
        ];
    }
}
