<?php

declare(strict_types=1);

namespace App\Http\Controllers\Dashboard;

use App\Actions\Dashboard\BuildDashboardDataAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Dashboard\ShowDateRequest;
use Illuminate\Http\JsonResponse;
use Inertia\Inertia;
use Inertia\Response;

class UserDashboardController extends Controller
{
    public function __invoke(ShowDateRequest $request, BuildDashboardDataAction $action): Response
    {
        $payload = $action->execute($request->validated('date'));

        return Inertia::render('dashboard', [
            ...$payload,
            'token_count' => $request->user()?->token_count ?? 0,
        ]);
    }

    public function availability(ShowDateRequest $request, BuildDashboardDataAction $action): JsonResponse
    {
        $payload = $action->execute($request->validated('date'));

        return response()->json([
            'data' => [
                ...$payload,
                'token_count' => $request->user()?->token_count ?? 0,
            ],
        ]);
    }
}
