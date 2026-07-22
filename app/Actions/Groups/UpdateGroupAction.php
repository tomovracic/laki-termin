<?php

declare(strict_types=1);

namespace App\Actions\Groups;

use App\DTO\Groups\UpdateGroupData;
use App\Models\Group;

class UpdateGroupAction
{
    public function execute(Group $group, UpdateGroupData $data): Group
    {
        $group->fill([
            'name' => $data->name,
            'color' => $data->color,
            'can_access_ranking' => $data->canAccessRanking,
            'can_view_all_ranking_groups' => $data->canViewAllRankingGroups,
            'can_access_match_history' => $data->canAccessMatchHistory,
            'can_view_all_match_history_groups' => $data->canViewAllMatchHistoryGroups,
        ])->save();

        return $group->refresh();
    }
}
