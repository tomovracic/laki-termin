<?php

declare(strict_types=1);

namespace App\Http\Controllers\TerrainSettings;

use App\Http\Controllers\Controller;
use App\Http\Requests\TerrainSettings\UpsertTerrainSettingRequest;
use App\Http\Resources\TerrainSettingResource;
use App\Models\TerrainSetting;

class TerrainSettingController extends Controller
{
    public function upsert(UpsertTerrainSettingRequest $request): TerrainSettingResource
    {
        $setting = TerrainSetting::query()->updateOrCreate(
            [
                'terrain_id' => null,
                'is_global' => true,
            ],
            $request->validated(),
        );

        return TerrainSettingResource::make($setting);
    }
}
