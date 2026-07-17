<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\League;
use App\Models\User;

class LeaguePolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, League $league): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return $user->hasRole('admin');
    }

    public function update(User $user, League $league): bool
    {
        return $user->hasRole('admin');
    }

    public function delete(User $user, League $league): bool
    {
        return $user->hasRole('admin');
    }

    public function manageParticipants(User $user, League $league): bool
    {
        return $user->hasRole('admin');
    }

    public function recordResult(User $user, League $league): bool
    {
        return $user->hasRole('admin');
    }

    public function finishRound(User $user, League $league): bool
    {
        return $user->hasRole('admin');
    }
}
