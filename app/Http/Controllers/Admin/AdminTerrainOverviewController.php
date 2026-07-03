<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\TerrainInactivePeriodResource;
use App\Models\Terrain;
use App\Models\TerrainInactivePeriod;
use App\Models\TerrainSetting;
use App\Models\User;
use App\Services\AppSettingService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class AdminTerrainOverviewController extends Controller
{
    public function __invoke(AppSettingService $appSettingService): Response
    {
        Gate::authorize('viewAny', User::class);

        $terrains = Terrain::query()
            ->orderBy('name')
            ->get(['id', 'name', 'code', 'description', 'is_active', 'created_at'])
            ->map(fn (Terrain $terrain): array => [
                'id' => $terrain->id,
                'name' => $terrain->name,
                'code' => $terrain->code,
                'description' => $terrain->description,
                'is_active' => $terrain->is_active,
                'created_at' => $terrain->created_at?->toISOString(),
            ])
            ->all();

        $globalSetting = TerrainSetting::query()
            ->global()
            ->first([
                'max_advance_days',
                'cancellation_cutoff_hours',
                'availability_periods',
            ]);

        $inactivePeriodsCutoff = CarbonImmutable::now('Europe/Zagreb')
            ->subDays(7)
            ->startOfDay()
            ->toDateTimeString();

        $inactivePeriods = TerrainInactivePeriod::query()
            ->with('terrain:id,name')
            ->where('to_at', '>=', $inactivePeriodsCutoff)
            ->orderBy('from_at')
            ->get();

        return Inertia::render('admin/terrains', [
            'terrains' => $terrains,
            'global_setting' => $globalSetting === null
                ? null
                : [
                    'max_advance_days' => $globalSetting->max_advance_days,
                    'cancellation_cutoff_hours' => $globalSetting->cancellation_cutoff_hours ?? 0,
                    'availability_periods' => $globalSetting->availability_periods ?? [],
                ],
            'inactive_periods' => TerrainInactivePeriodResource::collection($inactivePeriods)->resolve(),
            'terrain_usage_rules' => $appSettingService->getTerrainUsageRules(),
        ]);
    }
}
