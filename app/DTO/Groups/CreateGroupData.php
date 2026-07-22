<?php

declare(strict_types=1);

namespace App\DTO\Groups;

use App\Enums\GroupColor;

readonly class CreateGroupData
{
    public function __construct(
        public string $name,
        public GroupColor $color,
        public bool $canAccessRanking,
        public bool $canViewAllRankingGroups,
        public bool $canAccessMatchHistory,
        public bool $canViewAllMatchHistoryGroups,
    ) {}
}
