<?php

declare(strict_types=1);

namespace App\Services\Groups;

use App\Models\Group;
use App\Models\User;
use Illuminate\Support\Collection;

class UserGroupPermissionResolver
{
    public function canAccessMatchHistory(User $user): bool
    {
        if ($user->hasRole('admin')) {
            return true;
        }

        return $this->groups($user)->isNotEmpty();
    }

    public function canAccessRanking(User $user): bool
    {
        if ($user->hasRole('admin')) {
            return true;
        }

        return $this->groups($user)->contains(
            fn (Group $group): bool => $group->can_access_ranking,
        );
    }

    public function canViewAllRankingGroups(User $user): bool
    {
        if ($user->hasRole('admin')) {
            return true;
        }

        return $this->groups($user)->contains(
            fn (Group $group): bool => $group->can_view_all_ranking_groups,
        );
    }

    /**
     * @return list<int>
     */
    public function visibleRankingGroupIds(User $user): array
    {
        if ($this->canViewAllRankingGroups($user)) {
            return Group::query()
                ->orderBy('name')
                ->pluck('id')
                ->map(fn ($id): int => (int) $id)
                ->all();
        }

        return $this->groups($user)
            ->pluck('id')
            ->map(fn ($id): int => (int) $id)
            ->values()
            ->all();
    }

    /**
     * @return Collection<int, Group>
     */
    protected function groups(User $user): Collection
    {
        if ($user->relationLoaded('groups')) {
            return $user->groups;
        }

        return $user->groups()->get();
    }
}
