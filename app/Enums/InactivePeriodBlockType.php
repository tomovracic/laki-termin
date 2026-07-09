<?php

declare(strict_types=1);

namespace App\Enums;

enum InactivePeriodBlockType: string
{
    case FullDay = 'full_day';
    case TimeRange = 'time_range';
}
