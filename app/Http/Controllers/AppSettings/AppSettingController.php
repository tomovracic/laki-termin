<?php

declare(strict_types=1);

namespace App\Http\Controllers\AppSettings;

use App\Http\Controllers\Controller;
use App\Http\Requests\AppSettings\UpdateLoginMessageRequest;
use App\Http\Requests\AppSettings\UpdateTerrainUsageRulesRequest;
use App\Http\Resources\AppSettingResource;
use App\Services\AppSettingService;

class AppSettingController extends Controller
{
    public function updateLoginMessage(
        UpdateLoginMessageRequest $request,
        AppSettingService $appSettingService,
    ): AppSettingResource {
        $setting = $appSettingService->updateLoginMessage(
            $request->validated('login_message'),
        );

        return AppSettingResource::make($setting);
    }

    public function updateTerrainUsageRules(
        UpdateTerrainUsageRulesRequest $request,
        AppSettingService $appSettingService,
    ): AppSettingResource {
        /** @var list<array{icon: string, text: string}> $rules */
        $rules = $request->validated('rules');

        $setting = $appSettingService->updateTerrainUsageRules($rules);

        return AppSettingResource::make($setting);
    }
}
