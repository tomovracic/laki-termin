<?php

declare(strict_types=1);

namespace App\Enums;

enum ReservationSlotStatus: string
{
    case Available = 'available';
    case Past = 'past';
    case Blocked = 'blocked';
    case Reserved = 'reserved';
    case Maintenance = 'maintenance';
}
