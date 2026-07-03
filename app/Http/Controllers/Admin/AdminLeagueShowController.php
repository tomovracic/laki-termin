<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Actions\Leagues\BuildLeaguePageDataAction;
use App\Http\Controllers\Controller;
use App\Models\League;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class AdminLeagueShowController extends Controller
{
    public function __invoke(League $league, BuildLeaguePageDataAction $action): Response
    {
        Gate::authorize('view', $league);
        Gate::authorize('update', $league);

        return Inertia::render('admin/leagues/show', $action->execute($league, includeAvailableUsers: true));
    }
}
