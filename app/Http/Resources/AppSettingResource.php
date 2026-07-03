<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Services\AppSettingService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\AppSetting */
class AppSettingResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $message = $this->login_message;
        /** @var AppSettingService $appSettingService */
        $appSettingService = app(AppSettingService::class);

        return [
            'login_message' => is_string($message) && trim($message) !== '' ? trim($message) : null,
            'terrain_usage_rules' => $appSettingService->getTerrainUsageRules(),
        ];
    }
}
