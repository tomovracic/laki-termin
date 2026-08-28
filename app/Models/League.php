<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\KnockoutDrawMode;
use App\Enums\LeagueFormat;
use App\Enums\LeagueParticipantMode;
use App\Enums\LeagueStage;
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
        'participant_mode',
        'rounds',
        'sets_best_of',
        'knockout_draw_mode',
        'qualify_per_group',
        'best_runners_up',
        'current_stage',
        'created_by',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'format' => LeagueFormat::class,
            'participant_mode' => LeagueParticipantMode::class,
            'sets_best_of' => 'integer',
            'rounds' => 'integer',
            'knockout_draw_mode' => KnockoutDrawMode::class,
            'qualify_per_group' => 'integer',
            'best_runners_up' => 'integer',
            'current_stage' => LeagueStage::class,
        ];
    }

    public function isRoundRobin(): bool
    {
        return $this->format === LeagueFormat::RoundRobin;
    }

    public function isKnockout(): bool
    {
        return $this->format === LeagueFormat::Knockout;
    }

    public function isGroupKnockout(): bool
    {
        return $this->format === LeagueFormat::GroupKnockout;
    }

    public function isGroupStage(): bool
    {
        return $this->format === LeagueFormat::GroupKnockout
            && $this->current_stage === LeagueStage::Group;
    }

    public function isInKnockoutStage(): bool
    {
        if ($this->format === LeagueFormat::Knockout) {
            return true;
        }

        return $this->format === LeagueFormat::GroupKnockout
            && $this->current_stage === LeagueStage::Knockout;
    }

    public function isDoubles(): bool
    {
        return $this->participant_mode === LeagueParticipantMode::Doubles;
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function groups(): HasMany
    {
        return $this->hasMany(LeagueGroup::class);
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
