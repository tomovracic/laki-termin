<?php

declare(strict_types=1);

namespace App\DTO\Users;

readonly class RegisterInvitedUserData
{
    public function __construct(
        public string $firstName,
        public string $lastName,
        public string $phone,
        public string $password,
    ) {}
}
