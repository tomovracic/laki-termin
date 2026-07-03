<?php

declare(strict_types=1);

namespace App\Actions\Leagues;

use App\DTO\Leagues\RecordLeagueMatchResultData;
use App\Enums\LeagueMatchStatus;
use App\Models\LeagueMatch;
use App\Services\Leagues\LeagueMatchResultValidator;
use Illuminate\Validation\ValidationException;

class RecordLeagueMatchResultAction
{
    public function __construct(
        protected LeagueMatchResultValidator $validator,
    ) {}

    public function execute(RecordLeagueMatchResultData $data): LeagueMatch
    {
        $match = LeagueMatch::query()->findOrFail($data->matchId);

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

        $match->forceFill([
            'set1_player_one_games' => $data->set1PlayerOneGames,
            'set1_player_two_games' => $data->set1PlayerTwoGames,
            'set2_player_one_games' => $data->set2PlayerOneGames,
            'set2_player_two_games' => $data->set2PlayerTwoGames,
            'set3_player_one_games' => $data->set3PlayerOneGames,
            'set3_player_two_games' => $data->set3PlayerTwoGames,
            'status' => LeagueMatchStatus::Played->value,
            'played_at' => now(),
            'entered_by' => $data->enteredBy,
        ])->save();

        return $match->load(['playerOne', 'playerTwo', 'enteredBy']);
    }
}
