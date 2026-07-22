<?php

declare(strict_types=1);

namespace App\DTO\MatchHistory;

readonly class UpdatePlayedMatchData
{
    public function __construct(
        public int $set1PlayerOneGames,
        public int $set1PlayerTwoGames,
        public int $set2PlayerOneGames,
        public int $set2PlayerTwoGames,
        public ?int $set3PlayerOneGames,
        public ?int $set3PlayerTwoGames,
        public bool $isPublic,
        public bool $isRanked,
    ) {}
}
