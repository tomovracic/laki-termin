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
        ?int $set2PlayerOneGames = null,
        ?int $set2PlayerTwoGames = null,
        ?int $set3PlayerOneGames = null,
        ?int $set3PlayerTwoGames = null,
        ?int $set4PlayerOneGames = null,
        ?int $set4PlayerTwoGames = null,
        ?int $set5PlayerOneGames = null,
        ?int $set5PlayerTwoGames = null,
        int $bestOf = 3,
    ): array {
        if (! in_array($bestOf, [1, 3, 5], true)) {
            return ['Dozvoljeni formati su best of 1, 3 ili 5.'];
        }

        $setsToWin = (int) ceil($bestOf / 2);
        $rawSets = [
            [$set1PlayerOneGames, $set1PlayerTwoGames],
            [$set2PlayerOneGames, $set2PlayerTwoGames],
            [$set3PlayerOneGames, $set3PlayerTwoGames],
            [$set4PlayerOneGames, $set4PlayerTwoGames],
            [$set5PlayerOneGames, $set5PlayerTwoGames],
        ];

        $errors = [];
        $playedSets = [];

        foreach ($rawSets as $index => [$playerOneGames, $playerTwoGames]) {
            $setNumber = $index + 1;
            $hasAny = $playerOneGames !== null || $playerTwoGames !== null;

            if (! $hasAny) {
                break;
            }

            if ($playerOneGames === null || $playerTwoGames === null) {
                $errors[] = "Set {$setNumber} mora imati oba rezultata.";

                return $errors;
            }

            if ($setNumber > $bestOf) {
                $errors[] = "Best of {$bestOf} ne dopusta set {$setNumber}.";

                return $errors;
            }

            $winner = $this->validateSet($playerOneGames, $playerTwoGames, "set{$setNumber}", $errors);

            if ($winner === null) {
                return $errors;
            }

            $playedSets[] = $winner;
        }

        for ($i = count($playedSets); $i < 5; $i++) {
            [$playerOneGames, $playerTwoGames] = $rawSets[$i];

            if ($playerOneGames !== null || $playerTwoGames !== null) {
                $errors[] = 'Setovi moraju biti uneseni redom bez praznina.';

                return $errors;
            }
        }

        if ($playedSets === []) {
            $errors[] = 'Potrebno je unijeti barem jedan set.';

            return $errors;
        }

        $playerOneSets = 0;
        $playerTwoSets = 0;

        foreach ($playedSets as $index => $winner) {
            if ($playerOneSets >= $setsToWin || $playerTwoSets >= $setsToWin) {
                $errors[] = 'Uneseno je previse setova nakon sto je mec vec zavrsen.';

                return $errors;
            }

            if ($winner === 1) {
                $playerOneSets++;
            } else {
                $playerTwoSets++;
            }
        }

        if ($playerOneSets !== $setsToWin && $playerTwoSets !== $setsToWin) {
            $errors[] = "Jedan igrac mora imati tocno {$setsToWin} dobivena seta.";

            return $errors;
        }

        $minimumSets = $setsToWin;
        $maximumSets = ($setsToWin * 2) - 1;

        if (count($playedSets) < $minimumSets || count($playedSets) > $maximumSets) {
            $errors[] = "Broj odigranih setova mora biti izmedu {$minimumSets} i {$maximumSets}.";
        }

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

    /**
     * @return 1|2
     */
    public function winnerSlot(LeagueMatch $match): int
    {
        $playerOneSets = 0;
        $playerTwoSets = 0;

        $sets = [
            [$match->set1_player_one_games, $match->set1_player_two_games],
            [$match->set2_player_one_games, $match->set2_player_two_games],
            [$match->set3_player_one_games, $match->set3_player_two_games],
            [$match->set4_player_one_games, $match->set4_player_two_games],
            [$match->set5_player_one_games, $match->set5_player_two_games],
        ];

        foreach ($sets as [$playerOneGames, $playerTwoGames]) {
            if ($playerOneGames === null || $playerTwoGames === null) {
                continue;
            }

            if ($playerOneGames > $playerTwoGames) {
                $playerOneSets++;
            } elseif ($playerTwoGames > $playerOneGames) {
                $playerTwoSets++;
            }
        }

        return $playerOneSets > $playerTwoSets ? 1 : 2;
    }

    public function winnerUserId(LeagueMatch $match): int
    {
        $slot = $this->winnerSlot($match);
        $userId = $slot === 1 ? $match->player_one_id : $match->player_two_id;

        if ($userId === null) {
            throw new \RuntimeException('Pobjednik meca nema korisnicki ID.');
        }

        return $userId;
    }
}
