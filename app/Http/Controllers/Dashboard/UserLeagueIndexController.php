<?php

declare(strict_types=1);

namespace App\Http\Controllers\Dashboard;

use App\Enums\LeagueMatchStatus;
use App\Http\Controllers\Controller;
use App\Models\League;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class UserLeagueIndexController extends Controller
{
    public function __invoke(): Response
    {
        Gate::authorize('viewAny', League::class);

        $canManage = Gate::allows('create', League::class);

        $leagues = League::query()
            ->withCount([
                'participants',
                'matches',
                'matches as played_matches_count' => fn ($query) => $query->where('status', LeagueMatchStatus::Played->value),
            ])
            ->orderByDesc('created_at')
            ->get()
            ->map(fn (League $league): array => [
                'id' => $league->id,
                'name' => $league->name,
                'format' => $league->format->value,
                'participant_mode' => $league->participant_mode->value,
                'rounds' => $league->rounds,
                'sets_best_of' => $league->sets_best_of,
                'participants_count' => $league->participants_count,
                'matches_count' => $league->matches_count,
                'played_matches_count' => $league->played_matches_count,
                'created_at' => $league->created_at?->toIso8601String(),
            ])
            ->all();

        $users = [];

        if ($canManage) {
            $users = User::query()
                ->registered()
                ->orderBy('first_name')
                ->orderBy('last_name')
                ->get(['id', 'first_name', 'last_name'])
                ->map(fn (User $user): array => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'first_name' => $user->first_name,
                    'last_name' => $user->last_name,
                ])
                ->all();
        }

        return Inertia::render('dashboard/leagues', [
            'leagues' => $leagues,
            'users' => $users,
            'can_manage' => $canManage,
        ]);
    }
}
