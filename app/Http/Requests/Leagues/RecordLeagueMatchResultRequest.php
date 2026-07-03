<?php

declare(strict_types=1);

namespace App\Http\Requests\Leagues;

use App\Models\League;
use App\Models\LeagueMatch;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class RecordLeagueMatchResultRequest extends FormRequest
{
    public function authorize(): bool
    {
        $league = $this->route('league');

        return $league instanceof League
            && ($this->user()?->can('recordResult', $league) ?? false);
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

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $league = $this->route('league');
            $match = $this->route('match');

            if (! $league instanceof League || ! $match instanceof LeagueMatch) {
                return;
            }

            if ($match->league_id !== $league->id) {
                $validator->errors()->add('match', 'Meč ne pripada odabranoj ligi.');
            }
        });
    }
}
