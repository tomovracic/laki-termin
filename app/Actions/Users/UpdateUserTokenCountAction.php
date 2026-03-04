<?php

declare(strict_types=1);

namespace App\Actions\Users;

use App\DTO\Users\UpdateUserTokenCountData;
use App\Models\User;

class UpdateUserTokenCountAction
{
    public function execute(User $user, UpdateUserTokenCountData $data): User
    {
        $user->update([
            'token_count' => $data->tokenCount,
        ]);

        return $user->refresh();
    }
}
