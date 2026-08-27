<?php

declare(strict_types=1);

namespace App\DTO\Leagues;

use App\Enums\KnockoutDrawMode;
use App\Enums\LeagueFormat;
use App\Enums\LeagueParticipantMode;

readonly class CreateLeagueData
{
    /**
     * @param  list<int>  $participantIds
     * @param  list<array{0: int, 1: int}>  $pairs
     * @param  list<LeagueParticipantInputData>  $participants
     * @param  list<LeagueGroupInputData>  $groups
     */
    public function __construct(
        public string $name,
        public int $rounds,
        public int $createdBy,
        public array $participantIds,
        public LeagueFormat $format = LeagueFormat::RoundRobin,
        public LeagueParticipantMode $participantMode = LeagueParticipantMode::Singles,
        public int $setsBestOf = 3,
        public KnockoutDrawMode $knockoutDrawMode = KnockoutDrawMode::Seeded,
        public array $pairs = [],
        public array $participants = [],
        public int $qualifyPerGroup = 1,
        public int $bestRunnersUp = 0,
        public array $groups = [],
    ) {}
}
