<?php

declare(strict_types=1);

namespace App\Actions\MatchHistory;

use App\DTO\MatchHistory\UpdatePlayedMatchData;
use App\Models\PlayedMatch;
use App\Services\Leagues\LeagueMatchResultValidator;
use Illuminate\Validation\ValidationException;

class UpdatePlayedMatchAction
{
    public function __construct(
        protected LeagueMatchResultValidator $validator,
    ) {}

    public function execute(PlayedMatch $playedMatch, UpdatePlayedMatchData $data): PlayedMatch
    {
        $errors = $this->validator->validate(
            $data->set1PlayerOneGames,
            $data->set1PlayerTwoGames,
            $data->set2PlayerOneGames,
            $data->set2PlayerTwoGames,
            $data->set3PlayerOneGames,
            $data->set3PlayerTwoGames,
        );

        if ($errors !== []) {
            throw ValidationException::withMessages([
                'result' => $errors,
            ]);
        }

        $playedMatch->forceFill([
            'set1_player_one_games' => $data->set1PlayerOneGames,
            'set1_player_two_games' => $data->set1PlayerTwoGames,
            'set2_player_one_games' => $data->set2PlayerOneGames,
            'set2_player_two_games' => $data->set2PlayerTwoGames,
            'set3_player_one_games' => $data->set3PlayerOneGames,
            'set3_player_two_games' => $data->set3PlayerTwoGames,
            'is_public' => $data->isPublic,
            'is_ranked' => $data->isRanked,
        ])->save();

        return $playedMatch->load(['playerOne', 'playerTwo', 'enteredBy']);
    }
}
