<?php

declare(strict_types=1);

namespace App\Actions\Users;

use App\DTO\Users\RegisterInvitedUserData;
use App\Enums\UserInvitationStatus;
use App\Models\User;

class RegisterInvitedUserAction
{
    public function execute(User $user, RegisterInvitedUserData $data): User
    {
        $user->forceFill([
            'first_name' => $data->firstName,
            'last_name' => $data->lastName,
            'phone' => $data->phone,
            'password' => $data->password,
            'invitation_status' => UserInvitationStatus::Active->value,
            'invitation_token_hash' => null,
            'invitation_expires_at' => null,
            'invitation_accepted_at' => now(),
        ])->save();

        return $user->refresh();
    }
}
