<?php

declare(strict_types=1);

namespace App\Enums;

enum ReservationTokenType: string
{
    case Confirm = 'confirm';
    case Cancel = 'cancel';
}
