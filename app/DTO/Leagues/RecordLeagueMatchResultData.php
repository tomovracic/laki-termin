<?php

declare(strict_types=1);

namespace App\DTO\Leagues;

readonly class RecordLeagueMatchResultData
{
    public function __construct(
        public int $matchId,
        public int $enteredBy,
        public int $set1PlayerOneGames,
        public int $set1PlayerTwoGames,
        public int $set2PlayerOneGames,
        public int $set2PlayerTwoGames,
        public ?int $set3PlayerOneGames,
        public ?int $set3PlayerTwoGames,
    ) {}
}
