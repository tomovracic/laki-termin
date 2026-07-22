<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\GroupColor;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Group extends Model
{
    /** @use HasFactory<\Database\Factories\GroupFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'color',
        'can_access_ranking',
        'can_view_all_ranking_groups',
        'can_access_match_history',
        'can_view_all_match_history_groups',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'color' => GroupColor::class,
            'can_access_ranking' => 'boolean',
            'can_view_all_ranking_groups' => 'boolean',
            'can_access_match_history' => 'boolean',
            'can_view_all_match_history_groups' => 'boolean',
        ];
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class)->withTimestamps();
    }
}
