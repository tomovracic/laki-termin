<?php

declare(strict_types=1);

namespace App\Actions\Ranking;

use App\DTO\Ranking\EloRankingEntryData;
use App\Models\Group;
use App\Models\User;
use App\Services\Groups\UserGroupPermissionResolver;
use App\Services\Ranking\EloRankingService;

class BuildEloRankingPageDataAction
{
    public function __construct(
        protected EloRankingService $eloRankingService,
        protected UserGroupPermissionResolver $permissionResolver,
    ) {}

    /**
     * @return list<array{
     *     id: int,
     *     name: string,
     *     color: string,
     *     color_hex: string,
     *     rankings: list<array<string, mixed>>
     * }>
     */
    public function execute(User $viewer): array
    {
        $visibleGroupIds = $this->permissionResolver->visibleRankingGroupIds($viewer);

        if ($visibleGroupIds === []) {
            return [];
        }

        $groups = Group::query()
            ->with('users:id')
            ->whereIn('id', $visibleGroupIds)
            ->orderBy('name')
            ->get();

        $entriesByUserId = collect($this->eloRankingService->build())
            ->keyBy(fn (EloRankingEntryData $entry): int => $entry->userId);

        $sections = [];

        foreach ($groups as $group) {
            $memberIds = $group->users->pluck('id')->all();
            $rankings = [];

            foreach ($memberIds as $memberId) {
                $entry = $entriesByUserId->get($memberId);
                if ($entry === null) {
                    continue;
                }

                $rankings[] = $this->mapEntry($entry);
            }

            if ($rankings === []) {
                continue;
            }

            usort(
                $rankings,
                static function (array $left, array $right): int {
                    return [$right['elo'], $right['wins'], $right['matches_played'], $left['first_name']]
                        <=> [$left['elo'], $left['wins'], $left['matches_played'], $right['first_name']];
                },
            );

            $sections[] = [
                'id' => $group->id,
                'name' => $group->name,
                'color' => $group->color->value,
                'color_hex' => $group->color->hex(),
                'rankings' => $rankings,
            ];
        }

        return $sections;
    }

    /**
     * @return array<string, mixed>
     */
    protected function mapEntry(EloRankingEntryData $entry): array
    {
        return [
            'user_id' => $entry->userId,
            'first_name' => $entry->firstName,
            'last_name' => $entry->lastName,
            'name' => $entry->name,
            'elo' => $entry->elo,
            'matches_played' => $entry->matchesPlayed,
            'wins' => $entry->wins,
            'losses' => $entry->losses,
        ];
    }
}
