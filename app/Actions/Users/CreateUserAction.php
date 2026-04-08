<?php

declare(strict_types=1);

namespace App\Actions\Users;

use App\DTO\Users\CreateUserData;
use App\Enums\UserInvitationStatus;
use App\Models\User;
use App\Notifications\UserInvitationNotification;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class CreateUserAction
{
    public function execute(CreateUserData $data): User
    {
        $email = Str::lower(trim($data->email));
        $invitationToken = Str::random(64);
        $invitationTokenHash = hash('sha256', $invitationToken);
        $invitationExpiresAt = now()->addDays(3);
        $invitedAt = now();

        $user = User::query()
            ->where('email', $email)
            ->first();

        if ($user === null) {
            $user = User::query()->create([
                'first_name' => '',
                'last_name' => '',
                'phone' => '',
                'email' => $email,
                'password' => Hash::make(Str::random(64)),
                'token_count' => 0,
                'invitation_status' => UserInvitationStatus::Pending->value,
                'invitation_token_hash' => $invitationTokenHash,
                'invited_at' => $invitedAt,
                'invitation_expires_at' => $invitationExpiresAt,
                'invitation_accepted_at' => null,
            ]);
        } elseif ($user->isPendingInvitation()) {
            $user->forceFill([
                'invitation_token_hash' => $invitationTokenHash,
                'invited_at' => $invitedAt,
                'invitation_expires_at' => $invitationExpiresAt,
                'invitation_accepted_at' => null,
            ])->save();
        } else {
            throw ValidationException::withMessages([
                'email' => ['Korisnik s ovom email adresom vec postoji.'],
            ]);
        }

        $user->notify(new UserInvitationNotification(
            invitationUrl: route('invitation.accept', ['token' => $invitationToken]),
            expiresAt: $invitationExpiresAt,
        ));

        return $user->refresh();
    }
}
