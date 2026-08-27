<?php

declare(strict_types=1);

namespace App\Actions\Leagues;

use App\Enums\KnockoutDrawMode;
use App\Enums\LeagueStage;
use App\Models\League;
use App\Services\Leagues\GroupQualificationService;
use App\Services\Leagues\KnockoutBracketGeneratorService;
use Illuminate\Database\DatabaseManager;
use Illuminate\Validation\ValidationException;

class StartKnockoutFromGroupsAction
{
    public function __construct(
        protected GroupQualificationService $qualificationService,
        protected KnockoutBracketGeneratorService $bracketGenerator,
        protected DatabaseManager $database,
    ) {}

    public function execute(League $league): League
    {
        return $this->database->transaction(function () use ($league): League {
            $league->load(['matches', 'participants.user', 'groups.participants']);

            if (! $league->isGroupKnockout()) {
                throw ValidationException::withMessages([
                    'stage' => ['Knockout se moze pokrenuti samo iz grupnog turnira.'],
                ]);
            }

            if (! $league->isGroupStage()) {
                throw ValidationException::withMessages([
                    'stage' => ['Knockout je vec pokrenut za ovaj turnir.'],
                ]);
            }

            if (! $this->qualificationService->isGroupStageComplete($league)) {
                throw ValidationException::withMessages([
                    'stage' => ['Svi grupni mecevi moraju imati rezultat prije pokretanja knockouta.'],
                ]);
            }

            $qualifiers = $this->qualificationService->qualifyingParticipants($league);

            if (count($qualifiers) < 2) {
                throw ValidationException::withMessages([
                    'stage' => ['Nema dovoljno igraca za knockout fazu.'],
                ]);
            }

            foreach ($qualifiers as $index => $participant) {
                $participant->forceFill([
                    'seed' => $index + 1,
                    'received_bye' => false,
                ])->save();
            }

            $league->forceFill([
                'current_stage' => LeagueStage::Knockout->value,
                'knockout_draw_mode' => $league->knockout_draw_mode?->value ?? KnockoutDrawMode::Seeded->value,
            ])->save();

            $this->bracketGenerator->generate($league->fresh(), $qualifiers);

            $updated = $league->fresh([
                'matches.playerOne',
                'matches.playerTwo',
                'participants.user',
                'groups.participants.user',
            ]);

            return $updated ?? $league;
        });
    }
}
