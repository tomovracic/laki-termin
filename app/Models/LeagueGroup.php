<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LeagueGroup extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'league_id',
        'name',
        'sort_order',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'sort_order' => 'integer',
        ];
    }

    public function league(): BelongsTo
    {
        return $this->belongsTo(League::class);
    }

    public function participants(): HasMany
    {
        return $this->hasMany(LeagueParticipant::class);
    }

    public function matches(): HasMany
    {
        return $this->hasMany(LeagueMatch::class);
    }
}
