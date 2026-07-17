<?php

declare(strict_types=1);

namespace App\Actions\Leagues;

use App\Models\League;
use Illuminate\Database\DatabaseManager;

class DeleteLeagueAction
{
    public function __construct(
        protected DatabaseManager $database,
    ) {}

    public function execute(League $league): void
    {
        $this->database->transaction(function () use ($league): void {
            $league->matches()->update(['next_match_id' => null]);
            $league->matches()->delete();
            $league->participants()->delete();
            $league->delete();
        });
    }
}
