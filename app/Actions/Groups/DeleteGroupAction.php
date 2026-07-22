<?php

declare(strict_types=1);

namespace App\Actions\Groups;

use App\Models\Group;
use Illuminate\Support\Facades\DB;

class DeleteGroupAction
{
    public function execute(Group $group): void
    {
        DB::transaction(function () use ($group): void {
            $group->users()->detach();
            $group->delete();
        });
    }
}
