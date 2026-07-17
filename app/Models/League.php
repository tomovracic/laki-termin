<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\KnockoutDrawMode;
use App\Enums\LeagueFormat;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class League extends Model
{
    /** @use HasFactory<\Database\Factories\LeagueFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'format',
        'rounds',
        'sets_best_of',
        'knockout_draw_mode',
        'created_by',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'format' => LeagueFormat::class,
            'sets_best_of' => 'integer',
            'rounds' => 'integer',
            'knockout_draw_mode' => KnockoutDrawMode::class,
        ];
    }

    public function isKnockout(): bool
    {
        return $this->format === LeagueFormat::Knockout;
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
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
