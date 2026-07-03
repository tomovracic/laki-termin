<?php

declare(strict_types=1);

namespace App\DTO\Leagues;

readonly class CreateLeagueData
{
    /**
     * @param  list<int>  $participantIds
     */
    public function __construct(
        public string $name,
        public int $rounds,
        public int $createdBy,
        public array $participantIds,
    ) {}
}
