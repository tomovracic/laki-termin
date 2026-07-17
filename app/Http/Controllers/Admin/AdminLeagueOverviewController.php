<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Enums\LeagueMatchStatus;
use App\Http\Controllers\Controller;
use App\Models\League;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class AdminLeagueOverviewController extends Controller
{
    public function __invoke(): Response
    {
        Gate::authorize('viewAny', League::class);

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
                'rounds' => $league->rounds,
                'sets_best_of' => $league->sets_best_of,
                'participants_count' => $league->participants_count,
                'matches_count' => $league->matches_count,
                'played_matches_count' => $league->played_matches_count,
                'created_at' => $league->created_at?->toIso8601String(),
            ])
            ->all();

        $users = User::query()
            ->orderBy('first_name')
            ->orderBy('last_name')
            ->get(['id', 'first_name', 'last_name', 'email'])
            ->map(fn (User $user): array => [
                'id' => $user->id,
                'name' => $user->name,
                'first_name' => $user->first_name,
                'last_name' => $user->last_name,
                'email' => $user->email,
            ])
            ->all();

        return Inertia::render('admin/leagues', [
            'leagues' => $leagues,
            'users' => $users,
        ]);
    }
}
