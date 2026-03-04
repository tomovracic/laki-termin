<?php

declare(strict_types=1);

namespace App\Http\Controllers\Terrains;

use App\Actions\Terrains\CreateTerrainAction;
use App\DTO\Terrains\CreateTerrainData;
use App\Http\Controllers\Controller;
use App\Http\Requests\Terrains\StoreTerrainRequest;
use App\Http\Requests\Terrains\UpdateTerrainRequest;
use App\Http\Resources\TerrainResource;
use App\Models\Terrain;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class TerrainController extends Controller
{
    public function index(): AnonymousResourceCollection
    {
        $terrains = Terrain::query()
            ->orderBy('name')
            ->paginate(20);

        return TerrainResource::collection($terrains);
    }

    public function store(StoreTerrainRequest $request, CreateTerrainAction $action): TerrainResource
    {
        $validated = $request->validated();
        $terrain = $action->execute(new CreateTerrainData(
            name: (string) $validated['name'],
            description: $validated['description'] ?? null,
        ));

        return TerrainResource::make($terrain);
    }

    public function update(UpdateTerrainRequest $request, Terrain $terrain): TerrainResource
    {
        $terrain->update($request->validated());

        return TerrainResource::make($terrain);
    }
}
