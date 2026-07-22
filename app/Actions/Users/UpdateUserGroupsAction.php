<?php

declare(strict_types=1);

namespace App\Actions\Users;

use App\DTO\Users\UpdateUserGroupsData;
use App\Models\User;

class UpdateUserGroupsAction
{
    public function execute(User $user, UpdateUserGroupsData $data): User
    {
        $user->groups()->sync($data->groupIds);

        return $user->refresh()->load('groups');
    }
}
