<?php

declare(strict_types=1);

namespace App\Actions\AppSettings;

use App\Models\User;
use App\Services\AppSettingService;
use Illuminate\Support\Facades\Date;

class AcknowledgeLoginMessageAction
{
    public function __construct(
        private readonly AppSettingService $appSettingService,
    ) {}

    public function execute(User $user): User
    {
        if ($user->hasAcknowledgedCurrentLoginMessage($this->appSettingService->getLoginMessageUpdatedAt())) {
            return $user;
        }

        $user->login_message_acknowledged_at = Date::now();
        $user->save();

        return $user->refresh();
    }
}
