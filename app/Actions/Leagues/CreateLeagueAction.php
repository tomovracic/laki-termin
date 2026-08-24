<?php

declare(strict_types=1);

namespace App\Actions\Leagues;

use App\DTO\Leagues\CreateLeagueData;
use App\Enums\LeagueFormat;
use App\Enums\LeagueParticipantMode;
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
                'participant_mode' => LeagueParticipantMode::Singles->value,
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
        if ($data->participantMode === LeagueParticipantMode::Doubles) {
            return $this->createKnockoutDoubles($data);
        }

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
                'participant_mode' => LeagueParticipantMode::Singles->value,
                'rounds' => 1,
                'sets_best_of' => $data->setsBestOf,
                'knockout_draw_mode' => $data->knockoutDrawMode->value,
                'created_by' => $data->createdBy,
            ]);

            $createdParticipants = [];

            foreach ($participantIds as $index => $userId) {
                $createdParticipants[] = LeagueParticipant::query()->create([
                    'league_id' => $league->id,
                    'user_id' => $userId,
                    'seed' => $index + 1,
                    'received_bye' => false,
                ]);
            }

            $this->bracketGenerator->generate($league, $createdParticipants);

            return $league->load(['participants.user', 'matches.playerOne', 'matches.playerTwo']);
        });
    }

    /**
     * @return list<array{0: int, 1: int}>
     */
    private function normalizedPairs(CreateLeagueData $data): array
    {
        $pairs = [];
        $seenUserIds = [];

        foreach ($data->pairs as $pair) {
            if (! is_array($pair) || count($pair) !== 2) {
                throw ValidationException::withMessages([
                    'pairs' => ['Svaki par mora imati tocno dva igraca.'],
                ]);
            }

            $firstId = (int) $pair[0];
            $secondId = (int) $pair[1];

            if ($firstId === $secondId) {
                throw ValidationException::withMessages([
                    'pairs' => ['Par mora imati dva razlicita igraca.'],
                ]);
            }

            foreach ([$firstId, $secondId] as $userId) {
                if (in_array($userId, $seenUserIds, true)) {
                    throw ValidationException::withMessages([
                        'pairs' => ['Isti korisnik ne moze biti u vise parova.'],
                    ]);
                }

                $seenUserIds[] = $userId;
            }

            $pairs[] = [$firstId, $secondId];
        }

        if (count($pairs) < 2) {
            throw ValidationException::withMessages([
                'pairs' => ['Turnir za parove mora imati najmanje dva para.'],
            ]);
        }

        $existingCount = User::query()
            ->whereIn('id', $seenUserIds)
            ->count();

        if ($existingCount !== count($seenUserIds)) {
            throw ValidationException::withMessages([
                'pairs' => ['Jedan ili vise odabranih korisnika ne postoji.'],
            ]);
        }

        return $pairs;
    }

    private function createKnockoutDoubles(CreateLeagueData $data): League
    {
        $pairs = $this->normalizedPairs($data);

        if (! in_array($data->setsBestOf, [1, 3, 5], true)) {
            throw ValidationException::withMessages([
                'sets_best_of' => ['Dozvoljeni formati su best of 1, 3 ili 5.'],
            ]);
        }

        return $this->database->transaction(function () use ($data, $pairs): League {
            $league = League::query()->create([
                'name' => trim($data->name),
                'format' => LeagueFormat::Knockout->value,
                'participant_mode' => LeagueParticipantMode::Doubles->value,
                'rounds' => 1,
                'sets_best_of' => $data->setsBestOf,
                'knockout_draw_mode' => $data->knockoutDrawMode->value,
                'created_by' => $data->createdBy,
            ]);

            $createdParticipants = [];

            foreach ($pairs as $index => $pair) {
                $createdParticipants[] = LeagueParticipant::query()->create([
                    'league_id' => $league->id,
                    'user_id' => $pair[0],
                    'partner_user_id' => $pair[1],
                    'seed' => $index + 1,
                    'received_bye' => false,
                ])->load(['user', 'partner']);
            }

            $this->bracketGenerator->generate($league, $createdParticipants);

            return $league->load([
                'participants.user',
                'participants.partner',
                'matches.playerOne',
                'matches.playerTwo',
                'matches.playerOnePartner',
                'matches.playerTwoPartner',
            ]);
        });
    }
}
