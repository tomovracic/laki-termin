<?php

declare(strict_types=1);

namespace App\Http\Requests\MatchHistory;

use App\Models\PlayedMatch;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePlayedMatchRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', PlayedMatch::class) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'player_two.user_id' => ['nullable', 'integer', Rule::exists('users', 'id')],
            'player_two.first_name' => ['nullable', 'string', 'max:255'],
            'player_two.last_name' => ['nullable', 'string', 'max:255'],
            'played_at' => ['nullable', 'date'],
            'is_public' => ['nullable', 'boolean'],
            'is_ranked' => ['nullable', 'boolean'],
            'set1_player_one_games' => ['required', 'integer', 'min:0'],
            'set1_player_two_games' => ['required', 'integer', 'min:0'],
            'set2_player_one_games' => ['required', 'integer', 'min:0'],
            'set2_player_two_games' => ['required', 'integer', 'min:0'],
            'set3_player_one_games' => ['nullable', 'integer', 'min:0'],
            'set3_player_two_games' => ['nullable', 'integer', 'min:0'],
        ];
    }
}
