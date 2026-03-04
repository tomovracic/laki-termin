<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TerrainSetting extends Model
{
    /** @use HasFactory<\Database\Factories\TerrainSettingFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'terrain_id',
        'is_global',
        'max_advance_days',
        'availability_periods',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_global' => 'boolean',
            'max_advance_days' => 'integer',
            'availability_periods' => 'array',
        ];
    }

    public function terrain(): BelongsTo
    {
        return $this->belongsTo(Terrain::class);
    }

    public function scopeGlobal(Builder $query): Builder
    {
        return $query->where('is_global', true);
    }
}
