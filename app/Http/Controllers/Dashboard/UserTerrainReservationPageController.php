<?php

declare(strict_types=1);

namespace App\Http\Controllers\Dashboard;

use App\Actions\Dashboard\BuildTerrainSlotsDataAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Dashboard\ShowDateRequest;
use App\Models\Terrain;
use Illuminate\Http\JsonResponse;
use Inertia\Inertia;
use Inertia\Response;

class UserTerrainReservationPageController extends Controller
{
    public function __invoke(
        ShowDateRequest $request,
        Terrain $terrain,
        BuildTerrainSlotsDataAction $action,
    ): Response {
        abort_unless($terrain->is_active, 404);

        $payload = $action->execute($terrain, $request->validated('date'));

        return Inertia::render('terrains/show', [
            ...$payload,
            'terrain' => [
                'id' => $terrain->id,
                'name' => $terrain->name,
                'code' => $terrain->code,
                'description' => $terrain->description,
            ],
            'token_count' => $request->user()?->token_count ?? 0,
        ]);
    }

    public function slots(
        ShowDateRequest $request,
        Terrain $terrain,
        BuildTerrainSlotsDataAction $action,
    ): JsonResponse {
        abort_unless($terrain->is_active, 404);

        $payload = $action->execute($terrain, $request->validated('date'));

        return response()->json([
            'data' => [
                ...$payload,
                'token_count' => $request->user()?->token_count ?? 0,
            ],
        ]);
    }
}
