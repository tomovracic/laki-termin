<?php

declare(strict_types=1);

namespace App\Actions\Leagues;

use App\DTO\Leagues\CreateLeagueData;
use App\DTO\Leagues\LeagueGroupInputData;
use App\DTO\Leagues\LeagueParticipantInputData;
use App\Enums\LeagueFormat;
use App\Enums\LeagueParticipantMode;
use App\Enums\LeagueStage;
use App\Models\League;
use App\Models\LeagueGroup;
use App\Models\LeagueParticipant;
use App\Models\User;
use App\Services\Leagues\GroupStageValidator;
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
        protected GroupStageValidator $groupStageValidator,
    ) {}

    public function execute(CreateLeagueData $data): League
    {
        if ($data->format === LeagueFormat::Knockout) {
            return $this->createKnockout($data);
        }

        if ($data->format === LeagueFormat::GroupKnockout) {
            return $this->createGroupKnockout($data);
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

        $inputs = $this->resolveSinglesInputs($data);

        if (count($inputs) < 2) {
            throw ValidationException::withMessages([
                'participants' => ['Turnir mora imati najmanje dva sudionika.'],
            ]);
        }

        $this->assertValidSinglesInputs($inputs);

        if (! in_array($data->setsBestOf, [1, 3, 5], true)) {
            throw ValidationException::withMessages([
                'sets_best_of' => ['Dozvoljeni formati su best of 1, 3 ili 5.'],
            ]);
        }

        return $this->database->transaction(function () use ($data, $inputs): League {
            $league = League::query()->create([
                'name' => trim($data->name),
                'format' => LeagueFormat::Knockout->value,
                'participant_mode' => LeagueParticipantMode::Singles->value,
                'rounds' => 1,
                'sets_best_of' => $data->setsBestOf,
                'knockout_draw_mode' => $data->knockoutDrawMode->value,
                'created_by' => $data->createdBy,
            ]);

            $createdParticipants = $this->createParticipants($league, $inputs);

            $this->bracketGenerator->generate($league, $createdParticipants);

            return $league->load(['participants.user', 'matches.playerOne', 'matches.playerTwo']);
        });
    }

    private function createGroupKnockout(CreateLeagueData $data): League
    {
        $inputs = $this->resolveSinglesInputs($data);
        $this->assertValidSinglesInputs($inputs);

        if (! in_array($data->setsBestOf, [1, 3, 5], true)) {
            throw ValidationException::withMessages([
                'sets_best_of' => ['Dozvoljeni formati su best of 1, 3 ili 5.'],
            ]);
        }

        $groupPayload = array_map(
            fn (LeagueGroupInputData $group): array => [
                'name' => $group->name,
                'participant_indexes' => $group->participantIndexes,
            ],
            $data->groups,
        );

        $this->groupStageValidator->validate(
            count($inputs),
            $groupPayload,
            $data->qualifyPerGroup,
            $data->bestRunnersUp,
        );

        return $this->database->transaction(function () use ($data, $inputs, $groupPayload): League {
            $league = League::query()->create([
                'name' => trim($data->name),
                'format' => LeagueFormat::GroupKnockout->value,
                'participant_mode' => LeagueParticipantMode::Singles->value,
                'rounds' => 1,
                'sets_best_of' => $data->setsBestOf,
                'knockout_draw_mode' => $data->knockoutDrawMode->value,
                'qualify_per_group' => $data->qualifyPerGroup,
                'best_runners_up' => $data->bestRunnersUp,
                'current_stage' => LeagueStage::Group->value,
                'created_by' => $data->createdBy,
            ]);

            $createdParticipants = $this->createParticipants($league, $inputs);

            foreach ($groupPayload as $sortOrder => $groupInput) {
                $group = LeagueGroup::query()->create([
                    'league_id' => $league->id,
                    'name' => $groupInput['name'],
                    'sort_order' => $sortOrder,
                ]);

                $groupParticipants = [];

                foreach ($groupInput['participant_indexes'] as $participantIndex) {
                    $participant = $createdParticipants[$participantIndex];
                    $participant->forceFill(['league_group_id' => $group->id])->save();
                    $groupParticipants[] = $participant;
                }

                $this->matchGenerator->generateForGroup($league, $group->id, $groupParticipants);
            }

            return $league->load([
                'groups.participants.user',
                'participants.user',
                'matches.playerOne',
                'matches.playerTwo',
            ]);
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

    /**
     * @return list<LeagueParticipantInputData>
     */
    private function resolveSinglesInputs(CreateLeagueData $data): array
    {
        if ($data->participants !== []) {
            return array_values($data->participants);
        }

        return array_map(
            fn (int $userId): LeagueParticipantInputData => new LeagueParticipantInputData(
                userId: $userId,
                firstName: null,
                lastName: null,
            ),
            array_values($data->participantIds),
        );
    }

    /**
     * @param  list<LeagueParticipantInputData>  $inputs
     */
    private function assertValidSinglesInputs(array $inputs): void
    {
        $seenUserIds = [];
        $userIdsToCheck = [];

        foreach ($inputs as $index => $input) {
            if ($input->userId !== null) {
                if (in_array($input->userId, $seenUserIds, true)) {
                    throw ValidationException::withMessages([
                        'participants' => ['Isti korisnik ne moze biti dodan vise puta.'],
                    ]);
                }

                $seenUserIds[] = $input->userId;
                $userIdsToCheck[] = $input->userId;

                continue;
            }

            $firstName = trim((string) $input->firstName);
            $lastName = trim((string) $input->lastName);

            if ($firstName === '' || $lastName === '') {
                throw ValidationException::withMessages([
                    "participants.{$index}" => ['Gost mora imati ime i prezime.'],
                ]);
            }
        }

        if ($userIdsToCheck === []) {
            return;
        }

        $existingCount = User::query()
            ->whereIn('id', $userIdsToCheck)
            ->count();

        if ($existingCount !== count(array_unique($userIdsToCheck))) {
            throw ValidationException::withMessages([
                'participants' => ['Jedan ili vise odabranih korisnika ne postoji.'],
            ]);
        }
    }

    /**
     * @param  list<LeagueParticipantInputData>  $inputs
     * @return list<LeagueParticipant>
     */
    private function createParticipants(League $league, array $inputs): array
    {
        $created = [];

        foreach ($inputs as $index => $input) {
            $isGuest = $input->userId === null;

            $created[] = LeagueParticipant::query()->create([
                'league_id' => $league->id,
                'user_id' => $input->userId,
                'first_name' => $isGuest ? trim((string) $input->firstName) : null,
                'last_name' => $isGuest ? trim((string) $input->lastName) : null,
                'seed' => $index + 1,
                'received_bye' => false,
            ]);
        }

        return $created;
    }
}
