<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Actions\AppSettings\FlashLoginMessageAction;
use App\Models\User;
use Illuminate\Auth\Events\Login;

class FlashLoginMessageOnAuthentication
{
    public function __construct(
        private readonly FlashLoginMessageAction $flashLoginMessageAction,
    ) {}

    public function handle(Login $event): void
    {
        $user = $event->user;

        if (! $user instanceof User) {
            return;
        }

        $this->flashLoginMessageAction->execute($user);
    }
}
