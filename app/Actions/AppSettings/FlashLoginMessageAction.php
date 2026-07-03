<?php

declare(strict_types=1);

namespace App\Actions\AppSettings;

use App\Services\AppSettingService;

class FlashLoginMessageAction
{
    public function __construct(
        private readonly AppSettingService $appSettingService,
    ) {}

    public function execute(): void
    {
        $message = $this->appSettingService->getLoginMessage();

        if ($message === null) {
            return;
        }

        session()->flash('login_message', $message);
    }
}
