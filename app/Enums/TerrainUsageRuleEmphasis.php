<?php

declare(strict_types=1);

namespace App\Enums;

enum TerrainUsageRuleEmphasis: string
{
    case Neutral = 'neutral';
    case Alert = 'alert';
    case Warning = 'warning';
}
