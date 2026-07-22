<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Group;
use App\Models\User;

class GroupPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasRole('admin');
    }

    public function create(User $user): bool
    {
        return $this->viewAny($user);
    }

    public function update(User $user, Group $group): bool
    {
        return $this->viewAny($user);
    }

    public function delete(User $user, Group $group): bool
    {
        return $this->viewAny($user);
    }
}
