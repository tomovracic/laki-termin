<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\PlayedMatch;
use App\Models\User;

class PlayedMatchPolicy
{
    public function create(User $user): bool
    {
        return true;
    }

    public function view(User $user, PlayedMatch $playedMatch): bool
    {
        return $playedMatch->player_one_user_id === $user->id
            || $playedMatch->player_two_user_id === $user->id;
    }

    public function update(User $user, PlayedMatch $playedMatch): bool
    {
        return $playedMatch->player_one_user_id === $user->id
            || $playedMatch->player_two_user_id === $user->id;
    }

    public function delete(User $user, PlayedMatch $playedMatch): bool
    {
        return $this->update($user, $playedMatch);
    }
}
