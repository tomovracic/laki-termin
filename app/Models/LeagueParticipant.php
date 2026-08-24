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
        'partner_user_id',
        'first_name',
        'last_name',
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

        $partnerName = $this->partner?->name;

        if ($partnerName !== null && $partnerName !== '') {
            return $primary === '' ? $partnerName : $primary.' / '.$partnerName;
        }

        return $primary;
    }

    public function isGuest(): bool
    {
        return $this->user_id === null;
    }
}
