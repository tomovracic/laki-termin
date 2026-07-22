<?php

declare(strict_types=1);

namespace App\Http\Controllers\Dashboard;

use App\Actions\MatchHistory\BuildMatchHistoryPageDataAction;
use App\Http\Controllers\Controller;
use App\Services\Groups\UserGroupPermissionResolver;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpKernel\Exception\HttpException;

class UserMatchHistoryController extends Controller
{
    public function __invoke(
        Request $request,
        BuildMatchHistoryPageDataAction $action,
        UserGroupPermissionResolver $permissionResolver,
    ): Response {
        $user = $request->user();
        if ($user === null || ! $permissionResolver->canAccessMatchHistory($user)) {
            throw new HttpException(403);
        }

        return Inertia::render('dashboard/match-history', [
            'matches' => $action->execute($user),
        ]);
    }
}
