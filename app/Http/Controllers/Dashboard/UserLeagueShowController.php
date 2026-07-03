<?php

declare(strict_types=1);

namespace App\Http\Controllers\Dashboard;

use App\Actions\Leagues\BuildLeaguePageDataAction;
use App\Http\Controllers\Controller;
use App\Models\League;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class UserLeagueShowController extends Controller
{
    public function __invoke(League $league, BuildLeaguePageDataAction $action): Response
    {
        Gate::authorize('view', $league);

        return Inertia::render('dashboard/leagues/show', $action->execute($league));
    }
}
