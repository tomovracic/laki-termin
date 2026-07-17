<?php

declare(strict_types=1);

namespace App\Enums;

enum KnockoutDrawMode: string
{
    case Random = 'random';
    case Seeded = 'seeded';
}
