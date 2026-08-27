<?php

declare(strict_types=1);

namespace App\Actions\Leagues;

use App\DTO\Leagues\AddLeagueParticipantData;
use App\Models\League;
use App\Models\LeagueParticipant;
use App\Models\User;
use App\Services\Leagues\LeagueMatchGeneratorService;
use Illuminate\Database\DatabaseManager;
use Illuminate\Validation\ValidationException;

class AddLeagueParticipantAction
{
    public function __construct(
        protected DatabaseManager $database,
        protected LeagueMatchGeneratorService $matchGenerator,
    ) {}

    public function execute(AddLeagueParticipantData $data): LeagueParticipant
    {
        $league = League::query()->findOrFail($data->leagueId);

        if ($league->isKnockout() || $league->isGroupKnockout()) {
            throw ValidationException::withMessages([
                'user_id' => ['Sudionici se ne mogu dodavati u turnir nakon kreiranja.'],
            ]);
        }

        if (! User::query()->whereKey($data->userId)->exists()) {
            throw ValidationException::withMessages([
                'user_id' => ['Korisnik ne postoji.'],
            ]);
        }

        $alreadyParticipant = LeagueParticipant::query()
            ->where('league_id', $league->id)
            ->where('user_id', $data->userId)
            ->exists();

        if ($alreadyParticipant) {
            throw ValidationException::withMessages([
                'user_id' => ['Korisnik je vec sudionik ove lige.'],
            ]);
        }

        return $this->database->transaction(function () use ($league, $data): LeagueParticipant {
            $participant = LeagueParticipant::query()->create([
                'league_id' => $league->id,
                'user_id' => $data->userId,
            ]);

            $existingParticipantIds = LeagueParticipant::query()
                ->where('league_id', $league->id)
                ->where('user_id', '!=', $data->userId)
                ->pluck('user_id')
                ->all();

            $this->matchGenerator->generateForNewParticipant($league, $data->userId, $existingParticipantIds);

            return $participant->load('user');
        });
    }
}
