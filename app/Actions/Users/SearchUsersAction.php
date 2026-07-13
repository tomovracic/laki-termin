<?php

declare(strict_types=1);

namespace App\Actions\Users;

use App\Models\User;
use Illuminate\Database\Eloquent\Collection;

class SearchUsersAction
{
    /**
     * @return Collection<int, User>
     */
    public function execute(string $query, int $limit = 10): Collection
    {
        $term = trim($query);

        if ($term === '') {
            return new Collection;
        }

        $likeTerm = '%'.$term.'%';
        $fullNameExpression = $this->fullNameExpression();

        return User::query()
            ->where(function ($builder) use ($likeTerm, $fullNameExpression): void {
                $builder->where('first_name', 'like', $likeTerm)
                    ->orWhere('last_name', 'like', $likeTerm)
                    ->orWhereRaw("{$fullNameExpression} LIKE ?", [$likeTerm]);
            })
            ->orderBy('first_name')
            ->orderBy('last_name')
            ->limit($limit)
            ->get(['id', 'first_name', 'last_name', 'email']);
    }

    private function fullNameExpression(): string
    {
        $driver = User::query()->getConnection()->getDriverName();

        return match ($driver) {
            'sqlite' => "first_name || ' ' || last_name",
            default => "CONCAT(first_name, ' ', last_name)",
        };
    }
}
