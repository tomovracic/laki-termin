<?php

declare(strict_types=1);

namespace App\Enums;

enum InactivePeriodReason: string
{
    case Rain = 'rain';
    case Maintenance = 'maintenance';
    case Other = 'other';
}
