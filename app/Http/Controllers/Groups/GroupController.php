<?php

declare(strict_types=1);

namespace App\Http\Controllers\Groups;

use App\Actions\Groups\CreateGroupAction;
use App\Actions\Groups\DeleteGroupAction;
use App\Actions\Groups\UpdateGroupAction;
use App\DTO\Groups\CreateGroupData;
use App\DTO\Groups\UpdateGroupData;
use App\Enums\GroupColor;
use App\Http\Controllers\Controller;
use App\Http\Requests\Groups\StoreGroupRequest;
use App\Http\Requests\Groups\UpdateGroupRequest;
use App\Http\Resources\GroupResource;
use App\Models\Group;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;

class GroupController extends Controller
{
    public function store(StoreGroupRequest $request, CreateGroupAction $action): GroupResource
    {
        $validated = $request->validated();

        $group = $action->execute(new CreateGroupData(
            name: (string) $validated['name'],
            color: GroupColor::from((string) $validated['color']),
            canAccessRanking: (bool) $validated['can_access_ranking'],
            canViewAllRankingGroups: (bool) $validated['can_view_all_ranking_groups'],
            canAccessMatchHistory: (bool) $validated['can_access_match_history'],
            canViewAllMatchHistoryGroups: (bool) $validated['can_view_all_match_history_groups'],
        ));

        return GroupResource::make($group);
    }

    public function update(
        UpdateGroupRequest $request,
        Group $group,
        UpdateGroupAction $action,
    ): GroupResource {
        $validated = $request->validated();

        $updated = $action->execute($group, new UpdateGroupData(
            name: (string) $validated['name'],
            color: GroupColor::from((string) $validated['color']),
            canAccessRanking: (bool) $validated['can_access_ranking'],
            canViewAllRankingGroups: (bool) $validated['can_view_all_ranking_groups'],
            canAccessMatchHistory: (bool) $validated['can_access_match_history'],
            canViewAllMatchHistoryGroups: (bool) $validated['can_view_all_match_history_groups'],
        ));

        return GroupResource::make($updated);
    }

    public function destroy(Group $group, DeleteGroupAction $action): Response
    {
        Gate::authorize('delete', $group);

        $action->execute($group);

        return response()->noContent();
    }
}
