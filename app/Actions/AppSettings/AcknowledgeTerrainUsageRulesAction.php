<?php

declare(strict_types=1);

namespace App\Actions\AppSettings;

use App\Models\User;
use Illuminate\Support\Facades\Date;

class AcknowledgeTerrainUsageRulesAction
{
    public function execute(User $user): User
    {
        if ($user->terrain_usage_rules_acknowledged_at !== null) {
            return $user;
        }

        $user->terrain_usage_rules_acknowledged_at = Date::now();
        $user->save();

        return $user->refresh();
    }
}
