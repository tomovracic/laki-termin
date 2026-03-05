<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ReservationSlotStatus;
use App\Enums\ReservationStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class ReservationSlot extends Model
{
    /** @use HasFactory<\Database\Factories\ReservationSlotFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'terrain_id',
        'starts_at',
        'ends_at',
        'status',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'status' => ReservationSlotStatus::class,
        ];
    }

    public function terrain(): BelongsTo
    {
        return $this->belongsTo(Terrain::class);
    }

    public function reservation(): HasOne
    {
        return $this->hasOne(Reservation::class)
            ->whereIn('status', [
                ReservationStatus::Pending->value,
                ReservationStatus::Confirmed->value,
            ])
            ->latestOfMany();
    }

    public function scopeAvailable(Builder $query): Builder
    {
        return $query->where('status', ReservationSlotStatus::Available->value);
    }

    public function scopeForTerrain(Builder $query, int $terrainId): Builder
    {
        return $query->where('terrain_id', $terrainId);
    }

    public function scopeBetween(Builder $query, string $startsAt, string $endsAt): Builder
    {
        return $query
            ->where('starts_at', '>=', $startsAt)
            ->where('ends_at', '<=', $endsAt);
    }
}
