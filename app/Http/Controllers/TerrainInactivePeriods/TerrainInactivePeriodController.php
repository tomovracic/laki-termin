<?php

declare(strict_types=1);

namespace App\Http\Controllers\TerrainInactivePeriods;

use App\Http\Controllers\Controller;
use App\Http\Requests\TerrainInactivePeriods\StoreTerrainInactivePeriodRequest;
use App\Http\Resources\TerrainInactivePeriodResource;
use App\Models\TerrainInactivePeriod;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class TerrainInactivePeriodController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', TerrainInactivePeriod::class);

        $periods = TerrainInactivePeriod::query()
            ->with('terrain:id,name')
            ->when(
                $request->filled('terrain_id'),
                fn ($query) => $query->where('terrain_id', (int) $request->integer('terrain_id')),
            )
            ->latest('from_at')
            ->paginate(20);

        return TerrainInactivePeriodResource::collection($periods);
    }

    public function store(StoreTerrainInactivePeriodRequest $request): TerrainInactivePeriodResource
    {
        $period = TerrainInactivePeriod::query()->create([
            ...$request->validated(),
            'created_by' => $request->user()->id,
        ]);

        $period->load('terrain:id,name');

        return TerrainInactivePeriodResource::make($period);
    }

    public function destroy(TerrainInactivePeriod $terrainInactivePeriod): JsonResponse
    {
        $this->authorize('delete', $terrainInactivePeriod);
        $terrainInactivePeriod->delete();

        return response()->json([
            'data' => [
                'deleted' => true,
            ],
        ]);
    }
}
