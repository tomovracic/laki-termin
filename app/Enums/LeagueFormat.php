<?php

declare(strict_types=1);

namespace App\Enums;

enum LeagueFormat: string
{
    case RoundRobin = 'round_robin';
    case Knockout = 'knockout';
    case GroupKnockout = 'group_knockout';
}
