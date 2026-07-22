<?php

declare(strict_types=1);

namespace App\DTO\Users;

readonly class UpdateUserGroupsData
{
    /**
     * @param  list<int>  $groupIds
     */
    public function __construct(
        public array $groupIds,
    ) {}
}
