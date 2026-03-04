<?php

declare(strict_types=1);

namespace App\Enums;

enum InactivePeriodScope: string
{
    case Global = 'global';
    case Terrain = 'terrain';
}
