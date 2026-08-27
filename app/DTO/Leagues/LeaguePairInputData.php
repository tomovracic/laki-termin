<?php

declare(strict_types=1);

namespace App\DTO\Leagues;

readonly class LeaguePairInputData
{
    public function __construct(
        public LeagueParticipantInputData $playerOne,
        public LeagueParticipantInputData $playerTwo,
    ) {}
}
