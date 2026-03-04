<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Terrain;
use App\Models\User;

class TerrainPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasRole('admin') || $user->hasPermission('terrain.view');
    }

    public function view(User $user, Terrain $terrain): bool
    {
        return $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return $user->hasRole('admin');
    }

    public function update(User $user, Terrain $terrain): bool
    {
        return $this->create($user);
    }

    public function delete(User $user, Terrain $terrain): bool
    {
        return $this->create($user);
    }
}
