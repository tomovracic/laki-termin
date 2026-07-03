<?php

declare(strict_types=1);

namespace App\Enums;

enum TerrainUsageRuleIcon: string
{
    case Clock = 'clock';
    case Calendar = 'calendar';
    case Ban = 'ban';
    case Info = 'info';
    case AlertTriangle = 'alert_triangle';
    case CheckCircle = 'check_circle';
    case Coins = 'coins';
    case Users = 'users';
    case MapPin = 'map_pin';
    case Umbrella = 'umbrella';
    case Wrench = 'wrench';
    case Timer = 'timer';
    case Ticket = 'ticket';
}
