<?php

declare(strict_types=1);

namespace App\DTO\Users;

readonly class CreateUserData
{
    public function __construct(
        public string $email,
    ) {}
}
