<?php

declare(strict_types=1);

namespace App\Actions\MatchHistory;

use App\Models\PlayedMatch;

class DeletePlayedMatchAction
{
    public function execute(PlayedMatch $playedMatch): void
    {
        $playedMatch->delete();
    }
}
