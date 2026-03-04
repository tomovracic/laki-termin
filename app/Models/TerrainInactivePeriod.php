<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\InactivePeriodScope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TerrainInactivePeriod extends Model
{
    /** @use HasFactory<\Database\Factories\TerrainInactivePeriodFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'terrain_id',
        'created_by',
        'from_at',
        'to_at',
        'reason',
        'note',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'from_at' => 'datetime',
            'to_at' => 'datetime',
        ];
    }

    public function terrain(): BelongsTo
    {
        return $this->belongsTo(Terrain::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function scopeGlobal(Builder $query): Builder
    {
        return $query->whereNull('terrain_id');
    }

    public function scopeForTerrain(Builder $query, int $terrainId): Builder
    {
        return $query->where('terrain_id', $terrainId);
    }

    public function scopeOverlapping(Builder $query, string $startsAt, string $endsAt): Builder
    {
        return $query
            ->where('from_at', '<', $endsAt)
            ->where('to_at', '>', $startsAt);
    }

    public function scopeByScope(Builder $query, InactivePeriodScope $scope): Builder
    {
        return match ($scope) {
            InactivePeriodScope::Global => $query->whereNull('terrain_id'),
            InactivePeriodScope::Terrain => $query->whereNotNull('terrain_id'),
        };
    }
}
