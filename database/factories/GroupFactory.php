<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\GroupColor;
use App\Models\Group;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Group>
 */
class GroupFactory extends Factory
{
    protected $model = Group::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->words(2, true),
            'color' => fake()->randomElement(GroupColor::cases())->value,
            'can_access_ranking' => false,
            'can_view_all_ranking_groups' => false,
        ];
    }

    public function withRankingAccess(): static
    {
        return $this->state(fn (array $attributes): array => [
            'can_access_ranking' => true,
        ]);
    }

    public function withViewAllRankingGroups(): static
    {
        return $this->state(fn (array $attributes): array => [
            'can_access_ranking' => true,
            'can_view_all_ranking_groups' => true,
        ]);
    }
}
