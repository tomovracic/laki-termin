<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\PlayedMatch;
use App\Models\User;
use App\Services\Groups\UserGroupPermissionResolver;

class PlayedMatchPolicy
{
    public function __construct(
        private readonly UserGroupPermissionResolver $permissionResolver,
    ) {}

    public function create(User $user): bool
    {
        return $this->permissionResolver->canAccessMatchHistory($user);
    }

    public function view(User $user, PlayedMatch $playedMatch): bool
    {
        return $this->permissionResolver->canAccessMatchHistory($user)
            && $this->isParticipant($user, $playedMatch);
    }

    public function update(User $user, PlayedMatch $playedMatch): bool
    {
        return $this->permissionResolver->canAccessMatchHistory($user)
            && $this->isParticipant($user, $playedMatch);
    }

    public function delete(User $user, PlayedMatch $playedMatch): bool
    {
        return $this->update($user, $playedMatch);
    }

    private function isParticipant(User $user, PlayedMatch $playedMatch): bool
    {
        return $playedMatch->player_one_user_id === $user->id
            || $playedMatch->player_two_user_id === $user->id;
    }
}
