<?php

declare(strict_types=1);

namespace App\DTO\Users;

readonly class UpdateUserTokenCountData
{
    public function __construct(
        public int $tokenCount,
    ) {}
}
