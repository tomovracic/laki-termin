<?php

declare(strict_types=1);

namespace App\Http\Controllers\AppSettings;

use App\Http\Controllers\Controller;
use App\Http\Requests\AppSettings\DestroyTerrainUsageRuleRequest;
use App\Http\Requests\AppSettings\SaveTerrainUsageRuleRequest;
use App\Http\Requests\AppSettings\UpdateLoginMessageRequest;
use App\Http\Requests\AppSettings\UpdateTerrainUsageRulesRequest;
use App\Http\Resources\AppSettingResource;
use App\Services\AppSettingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

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

    public function storeTerrainUsageRule(
        SaveTerrainUsageRuleRequest $request,
        AppSettingService $appSettingService,
    ): JsonResponse {
        /** @var array{icon: string, text: string, emphasis?: string|null} $rule */
        $rule = $request->validated();

        $setting = $appSettingService->createTerrainUsageRule($rule);

        return AppSettingResource::make($setting)
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    public function updateTerrainUsageRule(
        SaveTerrainUsageRuleRequest $request,
        int $index,
        AppSettingService $appSettingService,
    ): AppSettingResource {
        /** @var array{icon: string, text: string, emphasis?: string|null} $rule */
        $rule = $request->validated();

        $setting = $appSettingService->updateTerrainUsageRule($index, $rule);

        return AppSettingResource::make($setting);
    }

    public function destroyTerrainUsageRule(
        DestroyTerrainUsageRuleRequest $request,
        int $index,
        AppSettingService $appSettingService,
    ): AppSettingResource {
        $setting = $appSettingService->deleteTerrainUsageRule($index);

        return AppSettingResource::make($setting);
    }
}
