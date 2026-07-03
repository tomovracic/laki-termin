<?php

declare(strict_types=1);

namespace App\Actions\Leagues;

use App\DTO\Leagues\CreateLeagueData;
use App\Models\League;
use App\Models\LeagueParticipant;
use App\Models\User;
use App\Services\Leagues\LeagueMatchGeneratorService;
use Illuminate\Database\DatabaseManager;
use Illuminate\Validation\ValidationException;

class CreateLeagueAction
{
    public function __construct(
        protected DatabaseManager $database,
        protected LeagueMatchGeneratorService $matchGenerator,
    ) {}

    public function execute(CreateLeagueData $data): League
    {
        $participantIds = array_values(array_unique($data->participantIds));

        if (count($participantIds) < 2) {
            throw ValidationException::withMessages([
                'participant_ids' => ['Liga mora imati najmanje dva sudionika.'],
            ]);
        }

        $existingCount = User::query()
            ->whereIn('id', $participantIds)
            ->count();

        if ($existingCount !== count($participantIds)) {
            throw ValidationException::withMessages([
                'participant_ids' => ['Jedan ili vise odabranih korisnika ne postoji.'],
            ]);
        }

        return $this->database->transaction(function () use ($data, $participantIds): League {
            $league = League::query()->create([
                'name' => trim($data->name),
                'rounds' => $data->rounds,
                'created_by' => $data->createdBy,
            ]);

            foreach ($participantIds as $userId) {
                LeagueParticipant::query()->create([
                    'league_id' => $league->id,
                    'user_id' => $userId,
                ]);
            }

            $this->matchGenerator->generateForAllParticipants($league, $participantIds);

            return $league->load(['participants.user', 'matches']);
        });
    }
}
