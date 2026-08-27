<?php

declare(strict_types=1);

namespace App\Actions\Leagues;

use App\Models\League;
use App\Services\Leagues\KnockoutBracketGeneratorService;
use Illuminate\Database\DatabaseManager;
use Illuminate\Validation\ValidationException;

class FinishKnockoutRoundAction
{
    public function __construct(
        protected KnockoutBracketGeneratorService $bracketGenerator,
        protected DatabaseManager $database,
    ) {}

    public function execute(League $league): League
    {
        return $this->database->transaction(function () use ($league): League {
            $league->load(['matches', 'participants.user']);

            if (! $league->isInKnockoutStage()) {
                throw ValidationException::withMessages([
                    'round' => ['Završavanje kola dostupno je samo za knockout turnire.'],
                ]);
            }

            $currentRound = (int) $league->matches->max('bracket_round');

            if ($currentRound < 1) {
                throw ValidationException::withMessages([
                    'round' => ['Turnir nema kola za završiti.'],
                ]);
            }

            $roundMatches = $league->matches->where('bracket_round', $currentRound);

            if (! $this->bracketGenerator->isRoundComplete($roundMatches)) {
                throw ValidationException::withMessages([
                    'round' => ['Svi mečevi u trenutnom kolu moraju imati rezultat prije završetka kola.'],
                ]);
            }

            if ($this->bracketGenerator->isTerminalRound($roundMatches)) {
                throw ValidationException::withMessages([
                    'round' => ['Trenutno kolo je završno — nema sljedećeg kola za izvući.'],
                ]);
            }

            $this->bracketGenerator->tryGenerateNextRound($league->fresh(['matches', 'participants.user']));

            $updated = $league->fresh([
                'matches.playerOne',
                'matches.playerTwo',
                'participants.user',
            ]);

            if ($updated === null) {
                return $league;
            }

            $newMaxRound = (int) $updated->matches->max('bracket_round');

            if ($newMaxRound <= $currentRound) {
                throw ValidationException::withMessages([
                    'round' => ['Sljedeće kolo nije moglo biti izvučeno.'],
                ]);
            }

            return $updated;
        });
    }
}
