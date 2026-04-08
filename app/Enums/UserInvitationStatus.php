<?php

declare(strict_types=1);

namespace App\Enums;

enum UserInvitationStatus: string
{
    case Pending = 'pending';
    case Active = 'active';
}
