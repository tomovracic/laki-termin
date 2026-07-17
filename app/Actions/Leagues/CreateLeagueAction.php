<?php

declare(strict_types=1);

namespace App\Actions\Leagues;

use App\DTO\Leagues\CreateLeagueData;
use App\Enums\LeagueFormat;
use App\Models\League;
use App\Models\LeagueParticipant;
use App\Models\User;
use App\Services\Leagues\KnockoutBracketGeneratorService;
use App\Services\Leagues\LeagueMatchGeneratorService;
use Illuminate\Database\DatabaseManager;
use Illuminate\Validation\ValidationException;

class CreateLeagueAction
{
    public function __construct(
        protected DatabaseManager $database,
        protected LeagueMatchGeneratorService $matchGenerator,
        protected KnockoutBracketGeneratorService $bracketGenerator,
    ) {}

    public function execute(CreateLeagueData $data): League
    {
        if ($data->format === LeagueFormat::Knockout) {
            return $this->createKnockout($data);
        }

        return $this->createRoundRobin($data);
    }

    private function createRoundRobin(CreateLeagueData $data): League
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
                'format' => LeagueFormat::RoundRobin->value,
                'rounds' => $data->rounds,
                'sets_best_of' => $data->setsBestOf,
                'created_by' => $data->createdBy,
            ]);

            foreach ($participantIds as $index => $userId) {
                LeagueParticipant::query()->create([
                    'league_id' => $league->id,
                    'user_id' => $userId,
                    'seed' => $index + 1,
                ]);
            }

            $this->matchGenerator->generateForAllParticipants($league, $participantIds);

            return $league->load(['participants.user', 'matches']);
        });
    }

    private function createKnockout(CreateLeagueData $data): League
    {
        $participantIds = array_values($data->participantIds);

        if (count($participantIds) < 2) {
            throw ValidationException::withMessages([
                'participant_ids' => ['Turnir mora imati najmanje dva sudionika.'],
            ]);
        }

        if (count(array_unique($participantIds)) !== count($participantIds)) {
            throw ValidationException::withMessages([
                'participant_ids' => ['Isti korisnik ne moze biti dodan vise puta.'],
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

        if (! in_array($data->setsBestOf, [1, 3, 5], true)) {
            throw ValidationException::withMessages([
                'sets_best_of' => ['Dozvoljeni formati su best of 1, 3 ili 5.'],
            ]);
        }

        return $this->database->transaction(function () use ($data, $participantIds): League {
            $league = League::query()->create([
                'name' => trim($data->name),
                'format' => LeagueFormat::Knockout->value,
                'rounds' => 1,
                'sets_best_of' => $data->setsBestOf,
                'created_by' => $data->createdBy,
            ]);

            $createdParticipants = [];

            foreach ($participantIds as $index => $userId) {
                $createdParticipants[] = LeagueParticipant::query()->create([
                    'league_id' => $league->id,
                    'user_id' => $userId,
                    'seed' => $index + 1,
                ]);
            }

            $this->bracketGenerator->generate($league, $createdParticipants);

            return $league->load(['participants.user', 'matches.playerOne', 'matches.playerTwo']);
        });
    }
}
