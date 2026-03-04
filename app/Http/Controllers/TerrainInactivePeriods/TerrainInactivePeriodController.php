<?php

declare(strict_types=1);

namespace App\Http\Controllers\TerrainInactivePeriods;

use App\Http\Controllers\Controller;
use App\Http\Requests\TerrainInactivePeriods\StoreTerrainInactivePeriodRequest;
use App\Models\TerrainInactivePeriod;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Resources\Json\JsonResource;

class TerrainInactivePeriodController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', TerrainInactivePeriod::class);

        $periods = TerrainInactivePeriod::query()
            ->when(
                $request->filled('terrain_id'),
                fn ($query) => $query->where('terrain_id', (int) $request->integer('terrain_id')),
            )
            ->latest('from_at')
            ->paginate(20);

        return JsonResource::collection($periods);
    }

    public function store(StoreTerrainInactivePeriodRequest $request): JsonResource
    {
        $period = TerrainInactivePeriod::query()->create([
            ...$request->validated(),
            'created_by' => $request->user()->id,
        ]);

        return JsonResource::make($period);
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
