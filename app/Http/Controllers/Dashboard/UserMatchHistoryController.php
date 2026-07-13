<?php

declare(strict_types=1);

namespace App\Http\Controllers\Dashboard;

use App\Actions\MatchHistory\BuildMatchHistoryPageDataAction;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class UserMatchHistoryController extends Controller
{
    public function __invoke(BuildMatchHistoryPageDataAction $action): Response
    {
        $user = Auth::user();

        return Inertia::render('dashboard/match-history', [
            'matches' => $action->execute($user),
        ]);
    }
}
