<?php

declare(strict_types=1);

namespace App\Actions\Ranking;

use App\Services\Ranking\EloRankingService;

class BuildEloRankingPageDataAction
{
    public function __construct(
        protected EloRankingService $eloRankingService,
    ) {}

    /**
     * @return list<array<string, mixed>>
     */
    public function execute(): array
    {
        return array_map(
            fn ($entry) => [
                'user_id' => $entry->userId,
                'first_name' => $entry->firstName,
                'last_name' => $entry->lastName,
                'name' => $entry->name,
                'elo' => $entry->elo,
                'matches_played' => $entry->matchesPlayed,
                'wins' => $entry->wins,
                'losses' => $entry->losses,
            ],
            $this->eloRankingService->build(),
        );
    }
}
