<?php

declare(strict_types=1);

namespace App\DTO\Leagues;

readonly class LeagueStandingsEntryData
{
    public function __construct(
        public int $participantId,
        public ?int $userId,
        public string $firstName,
        public string $lastName,
        public string $name,
        public int $matchesPlayed,
        public int $wins,
        public int $losses,
        public int $setsWon,
        public int $setsLost,
        public int $setDifference,
        public int $gamesWon = 0,
        public int $gamesLost = 0,
        public int $gameDifference = 0,
        public ?int $groupId = null,
        public ?string $groupName = null,
        public ?int $rankInGroup = null,
    ) {}
}
