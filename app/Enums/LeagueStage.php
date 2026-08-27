<?php

declare(strict_types=1);

namespace App\Enums;

enum LeagueStage: string
{
    case Group = 'group';
    case Knockout = 'knockout';
}
