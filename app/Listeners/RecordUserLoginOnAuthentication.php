<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Models\UserLoginLog;
use Illuminate\Auth\Events\Login;
use Illuminate\Http\Request;

class RecordUserLoginOnAuthentication
{
    public function __construct(
        private readonly Request $request,
    ) {}

    public function handle(Login $event): void
    {
        if (! $event->user instanceof \App\Models\User) {
            return;
        }

        UserLoginLog::query()->create([
            'user_id' => $event->user->id,
            'logged_in_at' => now(),
            'ip_address' => $this->request->ip(),
            'user_agent' => $this->request->userAgent(),
        ]);
    }
}
