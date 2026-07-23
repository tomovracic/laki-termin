<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\AppSettingService;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class AdminAppSettingsController extends Controller
{
    public function __invoke(AppSettingService $appSettingService): Response
    {
        Gate::authorize('viewAny', User::class);

        return Inertia::render('admin/app-settings', [
            'login_message' => $appSettingService->getLoginMessage(),
        ]);
    }
}
