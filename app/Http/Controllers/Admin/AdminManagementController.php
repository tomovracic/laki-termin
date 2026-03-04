<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Terrain;
use App\Models\TerrainSetting;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class AdminManagementController extends Controller
{
    public function __invoke(): Response
    {
        Gate::authorize('viewAny', User::class);

        $users = User::query()
            ->orderBy('first_name')
            ->orderBy('last_name')
            ->get(['id', 'first_name', 'last_name', 'email', 'phone', 'token_count', 'created_at'])
            ->map(fn (User $user): array => [
                'id' => $user->id,
                'first_name' => $user->first_name,
                'last_name' => $user->last_name,
                'name' => $user->name,
                'email' => $user->email,
                'phone' => $user->phone,
                'token_count' => $user->token_count ?? 0,
                'created_at' => $user->created_at?->toISOString(),
            ])
            ->all();

        $terrains = Terrain::query()
            ->orderBy('name')
            ->get(['id', 'name', 'code', 'description', 'is_active', 'created_at'])
            ->map(fn (Terrain $terrain): array => [
                'id' => $terrain->id,
                'name' => $terrain->name,
                'code' => $terrain->code,
                'description' => $terrain->description,
                'is_active' => $terrain->is_active,
                'created_at' => $terrain->created_at?->toISOString(),
            ])
            ->all();

        $globalSetting = TerrainSetting::query()
            ->global()
            ->first([
                'max_advance_days',
                'availability_periods',
            ]);

        return Inertia::render('admin/index', [
            'users' => $users,
            'terrains' => $terrains,
            'global_setting' => $globalSetting === null
                ? null
                : [
                    'max_advance_days' => $globalSetting->max_advance_days,
                    'availability_periods' => $globalSetting->availability_periods ?? [],
                ],
        ]);
    }
}
