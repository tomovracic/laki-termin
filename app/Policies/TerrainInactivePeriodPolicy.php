<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\TerrainInactivePeriod;
use App\Models\User;

class TerrainInactivePeriodPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasRole('admin') || $user->hasPermission('terrain.inactive.manage');
    }

    public function view(User $user, TerrainInactivePeriod $terrainInactivePeriod): bool
    {
        return $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return $this->viewAny($user);
    }

    public function update(User $user, TerrainInactivePeriod $terrainInactivePeriod): bool
    {
        return $this->viewAny($user);
    }

    public function delete(User $user, TerrainInactivePeriod $terrainInactivePeriod): bool
    {
        return $this->viewAny($user);
    }
}
