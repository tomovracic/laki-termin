<?php

declare(strict_types=1);

namespace App\Services\Leagues;

use App\Models\LeagueMatch;

class LeagueMatchResultValidator
{
    /**
     * @return list<string>
     */
    public function validate(
        int $set1PlayerOneGames,
        int $set1PlayerTwoGames,
        int $set2PlayerOneGames,
        int $set2PlayerTwoGames,
        ?int $set3PlayerOneGames,
        ?int $set3PlayerTwoGames,
    ): array {
        $errors = [];

        $setOneWinner = $this->validateSet($set1PlayerOneGames, $set1PlayerTwoGames, 'set1', $errors);
        $setTwoWinner = $this->validateSet($set2PlayerOneGames, $set2PlayerTwoGames, 'set2', $errors);

        if ($setOneWinner === null || $setTwoWinner === null) {
            return $errors;
        }

        $playerOneSets = ($setOneWinner === 1 ? 1 : 0) + ($setTwoWinner === 1 ? 1 : 0);
        $playerTwoSets = ($setOneWinner === 2 ? 1 : 0) + ($setTwoWinner === 2 ? 1 : 0);

        $hasSetThree = $set3PlayerOneGames !== null || $set3PlayerTwoGames !== null;

        if ($playerOneSets === 2 || $playerTwoSets === 2) {
            if ($hasSetThree) {
                $errors[] = 'Treci set nije potreban kada je mec zavrsen u dva seta.';
            }

            return $errors;
        }

        if ($playerOneSets === 1 && $playerTwoSets === 1) {
            if ($set3PlayerOneGames === null || $set3PlayerTwoGames === null) {
                $errors[] = 'Treci set je obavezan kada je rezultat 1-1 nakon prva dva seta.';

                return $errors;
            }

            $setThreeWinner = $this->validateSet($set3PlayerOneGames, $set3PlayerTwoGames, 'set3', $errors);

            if ($setThreeWinner === null) {
                return $errors;
            }

            $playerOneSets += $setThreeWinner === 1 ? 1 : 0;
            $playerTwoSets += $setThreeWinner === 2 ? 1 : 0;

            if ($playerOneSets !== 2 && $playerTwoSets !== 2) {
                $errors[] = 'Jedan igrac mora imati tocno dva dobivena seta.';
            }

            return $errors;
        }

        $errors[] = 'Nakon dva seta jedan igrac mora imati dva dobivena seta ili rezultat mora biti 1-1.';

        return $errors;
    }

    /**
     * @param  list<string>  $errors
     * @return 1|2|null player one or two
     */
    private function validateSet(int $playerOneGames, int $playerTwoGames, string $prefix, array &$errors): ?int
    {
        if ($playerOneGames < 0 || $playerTwoGames < 0) {
            $errors[] = "Gemovi u {$prefix} moraju biti nenegativni.";

            return null;
        }

        if ($playerOneGames === $playerTwoGames) {
            $errors[] = "Set {$prefix} ne moze zavrsiti nerijesenim rezultatom.";

            return null;
        }

        return $playerOneGames > $playerTwoGames ? 1 : 2;
    }

    public function winnerUserId(LeagueMatch $match): int
    {
        $playerOneSets = 0;
        $playerTwoSets = 0;

        if ($match->set1_player_one_games > $match->set1_player_two_games) {
            $playerOneSets++;
        } else {
            $playerTwoSets++;
        }

        if ($match->set2_player_one_games > $match->set2_player_two_games) {
            $playerOneSets++;
        } else {
            $playerTwoSets++;
        }

        if ($match->set3_player_one_games !== null && $match->set3_player_two_games !== null) {
            if ($match->set3_player_one_games > $match->set3_player_two_games) {
                $playerOneSets++;
            } else {
                $playerTwoSets++;
            }
        }

        return $playerOneSets > $playerTwoSets
            ? $match->player_one_id
            : $match->player_two_id;
    }
}
