<?php

declare(strict_types=1);

namespace App\Enums;

enum LeagueMatchStatus: string
{
    case Pending = 'pending';
    case Played = 'played';
}
