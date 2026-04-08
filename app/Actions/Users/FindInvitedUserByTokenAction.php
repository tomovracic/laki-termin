<?php

declare(strict_types=1);

namespace App\Actions\Users;

use App\Enums\UserInvitationStatus;
use App\Models\User;

class FindInvitedUserByTokenAction
{
    public function execute(string $token): ?User
    {
        return User::query()
            ->where('invitation_status', UserInvitationStatus::Pending->value)
            ->where('invitation_token_hash', hash('sha256', $token))
            ->where('invitation_expires_at', '>', now())
            ->first();
    }
}
