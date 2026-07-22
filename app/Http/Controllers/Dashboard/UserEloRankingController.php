<?php

declare(strict_types=1);

namespace App\Http\Controllers\Dashboard;

use App\Actions\Ranking\BuildEloRankingPageDataAction;
use App\Http\Controllers\Controller;
use Inertia\Inertia;
use Inertia\Response;

class UserEloRankingController extends Controller
{
    public function __invoke(BuildEloRankingPageDataAction $action): Response
    {
        return Inertia::render('dashboard/ranking', [
            'rankings' => $action->execute(),
        ]);
    }
}
