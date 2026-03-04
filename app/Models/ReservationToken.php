<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ReservationTokenType;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReservationToken extends Model
{
    /** @use HasFactory<\Database\Factories\ReservationTokenFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'reservation_id',
        'type',
        'token_hash',
        'expires_at',
        'used_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => ReservationTokenType::class,
            'expires_at' => 'datetime',
            'used_at' => 'datetime',
        ];
    }

    public function reservation(): BelongsTo
    {
        return $this->belongsTo(Reservation::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query
            ->whereNull('used_at')
            ->where('expires_at', '>', now());
    }

    public function scopeOfType(Builder $query, ReservationTokenType $type): Builder
    {
        return $query->where('type', $type->value);
    }
}
