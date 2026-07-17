<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\UserInvitationStatus;
use App\Models\User;
use Illuminate\Database\Seeder;

class UserSeeder extends Seeder
{
    /**
     * Seed 20 active users that can log in without invitations.
     *
     * Default password for all seeded users: password
     */
    public function run(): void
    {
        User::factory()
            ->count(20)
            ->create([
                'invitation_status' => UserInvitationStatus::Active->value,
                'invitation_token_hash' => null,
                'invited_at' => null,
                'invitation_expires_at' => null,
                'invitation_accepted_at' => now(),
                'token_count' => 10,
            ]);
    }
}
