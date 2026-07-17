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
        public ?int $set2PlayerOneGames = null,
        public ?int $set2PlayerTwoGames = null,
        public ?int $set3PlayerOneGames = null,
        public ?int $set3PlayerTwoGames = null,
        public ?int $set4PlayerOneGames = null,
        public ?int $set4PlayerTwoGames = null,
        public ?int $set5PlayerOneGames = null,
        public ?int $set5PlayerTwoGames = null,
    ) {}
}
