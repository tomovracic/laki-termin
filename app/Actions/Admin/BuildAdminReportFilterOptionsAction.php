<?php

declare(strict_types=1);

namespace App\Actions\Admin;

use App\Models\Terrain;
use App\Models\User;

class BuildAdminReportFilterOptionsAction
{
    /**
     * @return array{
     *     users: list<array{id: int, first_name: string, last_name: string, name: string, email: string}>,
     *     terrains: list<array{id: int, name: string, code: string}>
     * }
     */
    public function execute(): array
    {
        $users = User::query()
            ->orderBy('first_name')
            ->orderBy('last_name')
            ->get(['id', 'first_name', 'last_name', 'email'])
            ->map(fn (User $user): array => [
                'id' => $user->id,
                'first_name' => $user->first_name,
                'last_name' => $user->last_name,
                'name' => $user->name,
                'email' => $user->email,
            ])
            ->all();

        $terrains = Terrain::query()
            ->orderBy('name')
            ->get(['id', 'name', 'code'])
            ->map(fn (Terrain $terrain): array => [
                'id' => $terrain->id,
                'name' => $terrain->name,
                'code' => $terrain->code,
            ])
            ->all();

        return [
            'users' => $users,
            'terrains' => $terrains,
        ];
    }
}
