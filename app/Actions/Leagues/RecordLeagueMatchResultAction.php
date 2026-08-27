<?php

declare(strict_types=1);

namespace App\Actions\Leagues;

use App\DTO\Leagues\RecordLeagueMatchResultData;
use App\Enums\LeagueMatchStatus;
use App\Models\LeagueMatch;
use App\Services\Leagues\LeagueMatchResultValidator;
use Illuminate\Database\DatabaseManager;
use Illuminate\Validation\ValidationException;

class RecordLeagueMatchResultAction
{
    public function __construct(
        protected LeagueMatchResultValidator $validator,
        protected DatabaseManager $database,
    ) {}

    public function execute(RecordLeagueMatchResultData $data): LeagueMatch
    {
        return $this->database->transaction(function () use ($data): LeagueMatch {
            $match = LeagueMatch::query()->with('league')->findOrFail($data->matchId);

            if ($match->is_bye) {
                throw ValidationException::withMessages([
                    'result' => ['Bye mec nema rezultat za unos.'],
                ]);
            }

            if (! $match->hasPlayerOne() || ! $match->hasPlayerTwo()) {
                throw ValidationException::withMessages([
                    'result' => ['Oba igraca moraju biti poznata prije unosa rezultata.'],
                ]);
            }

            $bestOf = $match->league?->sets_best_of ?? 3;

            $errors = $this->validator->validate(
                $data->set1PlayerOneGames,
                $data->set1PlayerTwoGames,
                $data->set2PlayerOneGames,
                $data->set2PlayerTwoGames,
                $data->set3PlayerOneGames,
                $data->set3PlayerTwoGames,
                $data->set4PlayerOneGames,
                $data->set4PlayerTwoGames,
                $data->set5PlayerOneGames,
                $data->set5PlayerTwoGames,
                $bestOf,
            );

            if ($errors !== []) {
                throw ValidationException::withMessages([
                    'result' => $errors,
                ]);
            }

            if ($match->league?->isGroupKnockout() && $match->league->isInKnockoutStage() && $match->league_group_id !== null) {
                throw ValidationException::withMessages([
                    'result' => ['Rezultat grupnog meca se ne moze mijenjati nakon pokretanja knockouta.'],
                ]);
            }

            if ($match->league?->isInKnockoutStage() && $match->bracket_round !== null) {
                $this->assertKnockoutResultEditable($match);
            }

            $match->forceFill([
                'set1_player_one_games' => $data->set1PlayerOneGames,
                'set1_player_two_games' => $data->set1PlayerTwoGames,
                'set2_player_one_games' => $data->set2PlayerOneGames,
                'set2_player_two_games' => $data->set2PlayerTwoGames,
                'set3_player_one_games' => $data->set3PlayerOneGames,
                'set3_player_two_games' => $data->set3PlayerTwoGames,
                'set4_player_one_games' => $data->set4PlayerOneGames,
                'set4_player_two_games' => $data->set4PlayerTwoGames,
                'set5_player_one_games' => $data->set5PlayerOneGames,
                'set5_player_two_games' => $data->set5PlayerTwoGames,
                'status' => LeagueMatchStatus::Played->value,
                'played_at' => now(),
                'entered_by' => $data->enteredBy,
            ])->save();

            return $match->load(['playerOne', 'playerTwo', 'enteredBy', 'league']);
        });
    }

    private function assertKnockoutResultEditable(LeagueMatch $match): void
    {
        $league = $match->league;
        $league?->loadMissing('matches');

        $currentRound = (int) ($league?->matches->max('bracket_round') ?? 0);
        $matchRound = (int) ($match->bracket_round ?? $match->round);

        if ($currentRound > 0 && $matchRound < $currentRound) {
            throw ValidationException::withMessages([
                'result' => ['Rezultat se ne može mijenjati nakon što je kolo završeno.'],
            ]);
        }
    }
}
