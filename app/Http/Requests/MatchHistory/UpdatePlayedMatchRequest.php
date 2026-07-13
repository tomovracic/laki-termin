<?php

declare(strict_types=1);

namespace App\Http\Requests\MatchHistory;

use App\Models\PlayedMatch;
use Illuminate\Foundation\Http\FormRequest;

class UpdatePlayedMatchRequest extends FormRequest
{
    public function authorize(): bool
    {
        $playedMatch = $this->route('playedMatch');

        return $playedMatch instanceof PlayedMatch
            && ($this->user()?->can('update', $playedMatch) ?? false);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'set1_player_one_games' => ['required', 'integer', 'min:0'],
            'set1_player_two_games' => ['required', 'integer', 'min:0'],
            'set2_player_one_games' => ['required', 'integer', 'min:0'],
            'set2_player_two_games' => ['required', 'integer', 'min:0'],
            'set3_player_one_games' => ['nullable', 'integer', 'min:0'],
            'set3_player_two_games' => ['nullable', 'integer', 'min:0'],
        ];
    }
}
