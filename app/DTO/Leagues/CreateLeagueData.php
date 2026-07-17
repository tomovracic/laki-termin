<?php

declare(strict_types=1);

namespace App\DTO\Leagues;

use App\Enums\LeagueFormat;

readonly class CreateLeagueData
{
    /**
     * @param  list<int>  $participantIds
     * @param  list<LeagueParticipantInputData>  $participants
     */
    public function __construct(
        public string $name,
        public int $rounds,
        public int $createdBy,
        public array $participantIds,
        public LeagueFormat $format = LeagueFormat::RoundRobin,
        public int $setsBestOf = 3,
        public array $participants = [],
    ) {}
}
