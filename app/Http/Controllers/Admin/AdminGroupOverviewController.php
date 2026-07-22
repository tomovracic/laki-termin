<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Enums\GroupColor;
use App\Http\Controllers\Controller;
use App\Models\Group;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class AdminGroupOverviewController extends Controller
{
    public function __invoke(): Response
    {
        Gate::authorize('viewAny', Group::class);

        $groups = Group::query()
            ->withCount('users')
            ->orderBy('name')
            ->get()
            ->map(fn (Group $group): array => [
                'id' => $group->id,
                'name' => $group->name,
                'color' => $group->color->value,
                'color_hex' => $group->color->hex(),
                'can_access_ranking' => $group->can_access_ranking,
                'can_view_all_ranking_groups' => $group->can_view_all_ranking_groups,
                'users_count' => $group->users_count,
                'created_at' => $group->created_at?->toISOString(),
            ])
            ->all();

        return Inertia::render('admin/groups', [
            'groups' => $groups,
            'color_options' => GroupColor::options(),
        ]);
    }
}
