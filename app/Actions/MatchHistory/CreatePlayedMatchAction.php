<?php

declare(strict_types=1);

namespace App\Actions\MatchHistory;

use App\DTO\MatchHistory\CreatePlayedMatchData;
use App\DTO\MatchHistory\MatchHistoryPlayerInputData;
use App\Models\PlayedMatch;
use App\Services\Leagues\LeagueMatchResultValidator;
use Illuminate\Validation\ValidationException;

class CreatePlayedMatchAction
{
    public function __construct(
        protected LeagueMatchResultValidator $validator,
    ) {}

    public function execute(CreatePlayedMatchData $data): PlayedMatch
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

        $playerTwoErrors = $this->validatePlayer($data->playerTwo, 'player_two');

        if ($playerTwoErrors !== []) {
            throw ValidationException::withMessages($playerTwoErrors);
        }

        if ($data->playerTwo->userId === $data->currentUserId) {
            throw ValidationException::withMessages([
                'player_two' => 'Protivnik ne moze biti isti korisnik.',
            ]);
        }

        $playedMatch = PlayedMatch::query()->create([
            'player_one_user_id' => $data->currentUserId,
            'player_one_first_name' => null,
            'player_one_last_name' => null,
            'player_two_user_id' => $data->playerTwo->userId,
            'player_two_first_name' => $data->playerTwo->userId === null ? $data->playerTwo->firstName : null,
            'player_two_last_name' => $data->playerTwo->userId === null ? $data->playerTwo->lastName : null,
            'set1_player_one_games' => $data->set1PlayerOneGames,
            'set1_player_two_games' => $data->set1PlayerTwoGames,
            'set2_player_one_games' => $data->set2PlayerOneGames,
            'set2_player_two_games' => $data->set2PlayerTwoGames,
            'set3_player_one_games' => $data->set3PlayerOneGames,
            'set3_player_two_games' => $data->set3PlayerTwoGames,
            'played_at' => $data->playedAt,
            'entered_by' => $data->currentUserId,
        ]);

        return $playedMatch->load(['playerOne', 'playerTwo', 'enteredBy']);
    }

    /**
     * @return array<string, list<string>>
     */
    private function validatePlayer(MatchHistoryPlayerInputData $player, string $prefix): array
    {
        if ($player->userId !== null) {
            return [];
        }

        $errors = [];

        if ($player->firstName === null || trim($player->firstName) === '') {
            $errors[$prefix.'.first_name'] = ['Ime protivnika je obavezno.'];
        }

        if ($player->lastName === null || trim($player->lastName) === '') {
            $errors[$prefix.'.last_name'] = ['Prezime protivnika je obavezno.'];
        }

        return $errors;
    }
}
