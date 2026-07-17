<?php

declare(strict_types=1);

namespace App\Http\Controllers\Dashboard;

use App\Enums\LeagueMatchStatus;
use App\Http\Controllers\Controller;
use App\Models\League;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class UserLeagueIndexController extends Controller
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

        return Inertia::render('dashboard/leagues', [
            'leagues' => $leagues,
        ]);
    }
}
