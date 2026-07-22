<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\PlayedMatch;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Date;

/**
 * @extends Factory<PlayedMatch>
 */
class PlayedMatchFactory extends Factory
{
    protected $model = PlayedMatch::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'player_one_user_id' => User::factory(),
            'player_one_first_name' => null,
            'player_one_last_name' => null,
            'player_two_user_id' => User::factory(),
            'player_two_first_name' => null,
            'player_two_last_name' => null,
            'set1_player_one_games' => 6,
            'set1_player_two_games' => 4,
            'set2_player_one_games' => 6,
            'set2_player_two_games' => 3,
            'set3_player_one_games' => null,
            'set3_player_two_games' => null,
            'played_at' => Date::now(),
            'entered_by' => User::factory(),
            'is_public' => true,
            'is_ranked' => true,
        ];
    }

    public function withGuestOpponent(string $firstName = 'Gost', string $lastName = 'Igrac'): static
    {
        return $this->state(fn (): array => [
            'player_two_user_id' => null,
            'player_two_first_name' => $firstName,
            'player_two_last_name' => $lastName,
        ]);
    }
}
