<?php

declare(strict_types=1);

namespace App\DTO\Terrains;

readonly class CreateTerrainData
{
    public function __construct(
        public string $name,
        public ?string $description,
    ) {}
}
