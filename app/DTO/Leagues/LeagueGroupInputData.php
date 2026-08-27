<?php

declare(strict_types=1);

namespace App\DTO\Leagues;

readonly class LeagueGroupInputData
{
    /**
     * @param  list<int>  $participantIndexes
     */
    public function __construct(
        public string $name,
        public array $participantIndexes,
    ) {}
}
