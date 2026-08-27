<?php

declare(strict_types=1);

namespace App\Actions\Leagues;

use App\DTO\Leagues\CreateLeagueData;
use App\DTO\Leagues\LeagueGroupInputData;
use App\DTO\Leagues\LeaguePairInputData;
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
        if ($data->participantMode === LeagueParticipantMode::Doubles) {
            return $this->createRoundRobinDoubles($data);
        }

        $inputs = $this->resolveSinglesInputs($data);

        if (count($inputs) < 2) {
            throw ValidationException::withMessages([
                'participants' => ['Liga mora imati najmanje dva sudionika.'],
            ]);
        }

        $this->assertValidSinglesInputs($inputs);

        return $this->database->transaction(function () use ($data, $inputs): League {
            $league = League::query()->create([
                'name' => trim($data->name),
                'format' => LeagueFormat::RoundRobin->value,
                'participant_mode' => LeagueParticipantMode::Singles->value,
                'rounds' => $data->rounds,
                'sets_best_of' => $data->setsBestOf,
                'created_by' => $data->createdBy,
            ]);

            $createdParticipants = $this->createParticipants($league, $inputs);
            $this->matchGenerator->generateForAllParticipants($league, $createdParticipants);

            return $league->load(['participants.user', 'matches']);
        });
    }

    private function createRoundRobinDoubles(CreateLeagueData $data): League
    {
        $pairs = $this->normalizedPairs($data);

        return $this->database->transaction(function () use ($data, $pairs): League {
            $league = League::query()->create([
                'name' => trim($data->name),
                'format' => LeagueFormat::RoundRobin->value,
                'participant_mode' => LeagueParticipantMode::Doubles->value,
                'rounds' => $data->rounds,
                'sets_best_of' => $data->setsBestOf,
                'created_by' => $data->createdBy,
            ]);

            $createdParticipants = $this->createDoublesParticipants($league, $pairs);
            $this->matchGenerator->generateForAllParticipants($league, $createdParticipants);

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
        $this->assertValidSetsBestOf($data);

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
        $this->assertValidSetsBestOf($data);

        if ($data->participantMode === LeagueParticipantMode::Doubles) {
            return $this->createGroupKnockoutDoubles($data);
        }

        $inputs = $this->resolveSinglesInputs($data);
        $this->assertValidSinglesInputs($inputs);

        $groupPayload = $this->groupPayload($data);
        $this->groupStageValidator->validate(
            count($inputs),
            $groupPayload,
            $data->qualifyPerGroup,
            $data->bestRunnersUp,
        );

        return $this->database->transaction(function () use ($data, $inputs, $groupPayload): League {
            $league = $this->createGroupKnockoutLeague($data, LeagueParticipantMode::Singles);
            $createdParticipants = $this->createParticipants($league, $inputs);
            $this->assignGroupsAndGenerateMatches($league, $createdParticipants, $groupPayload);

            return $league->load([
                'groups.participants.user',
                'participants.user',
                'matches.playerOne',
                'matches.playerTwo',
            ]);
        });
    }

    private function createGroupKnockoutDoubles(CreateLeagueData $data): League
    {
        $pairs = $this->normalizedPairs($data, 4);
        $groupPayload = $this->groupPayload($data);
        $this->groupStageValidator->validate(
            count($pairs),
            $groupPayload,
            $data->qualifyPerGroup,
            $data->bestRunnersUp,
        );

        return $this->database->transaction(function () use ($data, $pairs, $groupPayload): League {
            $league = $this->createGroupKnockoutLeague($data, LeagueParticipantMode::Doubles);
            $createdParticipants = $this->createDoublesParticipants($league, $pairs);
            $this->assignGroupsAndGenerateMatches($league, $createdParticipants, $groupPayload);

            return $league->load([
                'groups.participants.user',
                'groups.participants.partner',
                'participants.user',
                'participants.partner',
                'matches.playerOne',
                'matches.playerTwo',
                'matches.playerOnePartner',
                'matches.playerTwoPartner',
            ]);
        });
    }

    private function createKnockoutDoubles(CreateLeagueData $data): League
    {
        $pairs = $this->normalizedPairs($data);
        $this->assertValidSetsBestOf($data);

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

            $createdParticipants = $this->createDoublesParticipants($league, $pairs);
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
     * @return list<LeaguePairInputData>
     */
    private function normalizedPairs(CreateLeagueData $data, int $minimum = 2): array
    {
        $pairs = [];
        $seenUserIds = [];
        $userIdsToCheck = [];

        foreach ($data->pairs as $index => $pair) {
            $normalized = $this->pairFromMixed($pair);

            if ($normalized === null) {
                throw ValidationException::withMessages([
                    'pairs' => ['Svaki par mora imati tocno dva igraca.'],
                ]);
            }

            if (! $this->isValidPlayer($normalized->playerOne)) {
                throw ValidationException::withMessages([
                    "pairs.{$index}.player_one" => ['Gost mora imati ime i prezime.'],
                ]);
            }

            if (! $this->isValidPlayer($normalized->playerTwo)) {
                throw ValidationException::withMessages([
                    "pairs.{$index}.player_two" => ['Gost mora imati ime i prezime.'],
                ]);
            }

            if (
                $normalized->playerOne->userId !== null
                && $normalized->playerOne->userId === $normalized->playerTwo->userId
            ) {
                throw ValidationException::withMessages([
                    'pairs' => ['Par mora imati dva razlicita igraca.'],
                ]);
            }

            foreach ([$normalized->playerOne->userId, $normalized->playerTwo->userId] as $userId) {
                if ($userId === null) {
                    continue;
                }

                if (in_array($userId, $seenUserIds, true)) {
                    throw ValidationException::withMessages([
                        'pairs' => ['Isti korisnik ne moze biti u vise parova.'],
                    ]);
                }

                $seenUserIds[] = $userId;
                $userIdsToCheck[] = $userId;
            }

            $pairs[] = $normalized;
        }

        if (count($pairs) < $minimum) {
            throw ValidationException::withMessages([
                'pairs' => [
                    $minimum > 2
                        ? 'Grupni turnir za parove mora imati najmanje cetiri para.'
                        : 'Turnir za parove mora imati najmanje dva para.',
                ],
            ]);
        }

        if ($userIdsToCheck !== []) {
            $existingCount = User::query()
                ->whereIn('id', $userIdsToCheck)
                ->count();

            if ($existingCount !== count(array_unique($userIdsToCheck))) {
                throw ValidationException::withMessages([
                    'pairs' => ['Jedan ili vise odabranih korisnika ne postoji.'],
                ]);
            }
        }

        return $pairs;
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

            if (! $this->isValidGuest($input)) {
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

    private function assertValidSetsBestOf(CreateLeagueData $data): void
    {
        if (! in_array($data->setsBestOf, [1, 3, 5], true)) {
            throw ValidationException::withMessages([
                'sets_best_of' => ['Dozvoljeni formati su best of 1, 3 ili 5.'],
            ]);
        }
    }

    /**
     * @return list<array{name: string, participant_indexes: list<int>}>
     */
    private function groupPayload(CreateLeagueData $data): array
    {
        return array_map(
            fn (LeagueGroupInputData $group): array => [
                'name' => $group->name,
                'participant_indexes' => $group->participantIndexes,
            ],
            $data->groups,
        );
    }

    private function createGroupKnockoutLeague(CreateLeagueData $data, LeagueParticipantMode $mode): League
    {
        return League::query()->create([
            'name' => trim($data->name),
            'format' => LeagueFormat::GroupKnockout->value,
            'participant_mode' => $mode->value,
            'rounds' => 1,
            'sets_best_of' => $data->setsBestOf,
            'knockout_draw_mode' => $data->knockoutDrawMode->value,
            'qualify_per_group' => $data->qualifyPerGroup,
            'best_runners_up' => $data->bestRunnersUp,
            'current_stage' => LeagueStage::Group->value,
            'created_by' => $data->createdBy,
        ]);
    }

    /**
     * @param  list<LeagueParticipant>  $createdParticipants
     * @param  list<array{name: string, participant_indexes: list<int>}>  $groupPayload
     */
    private function assignGroupsAndGenerateMatches(
        League $league,
        array $createdParticipants,
        array $groupPayload,
    ): void {
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
    }

    /**
     * @param  list<LeagueParticipantInputData>  $inputs
     * @return list<LeagueParticipant>
     */
    private function createParticipants(League $league, array $inputs): array
    {
        $created = [];

        foreach ($inputs as $index => $input) {
            $created[] = LeagueParticipant::query()->create(
                $this->participantAttributes($league, $input, $index + 1),
            );
        }

        return $created;
    }

    /**
     * @param  list<LeaguePairInputData>  $pairs
     * @return list<LeagueParticipant>
     */
    private function createDoublesParticipants(League $league, array $pairs): array
    {
        $created = [];

        foreach ($pairs as $index => $pair) {
            $created[] = LeagueParticipant::query()->create([
                ...$this->participantAttributes($league, $pair->playerOne, $index + 1),
                'partner_user_id' => $pair->playerTwo->userId,
                'partner_first_name' => $pair->playerTwo->userId === null
                    ? trim((string) $pair->playerTwo->firstName)
                    : null,
                'partner_last_name' => $pair->playerTwo->userId === null
                    ? trim((string) $pair->playerTwo->lastName)
                    : null,
            ])->load(['user', 'partner']);
        }

        return $created;
    }

    /**
     * @return array<string, mixed>
     */
    private function participantAttributes(
        League $league,
        LeagueParticipantInputData $input,
        int $seed,
    ): array {
        $isGuest = $input->userId === null;

        return [
            'league_id' => $league->id,
            'user_id' => $input->userId,
            'first_name' => $isGuest ? trim((string) $input->firstName) : null,
            'last_name' => $isGuest ? trim((string) $input->lastName) : null,
            'seed' => $seed,
            'received_bye' => false,
        ];
    }

    /**
     * @param  LeaguePairInputData|array{0: int, 1: int}  $pair
     */
    private function pairFromMixed(mixed $pair): ?LeaguePairInputData
    {
        if ($pair instanceof LeaguePairInputData) {
            return $pair;
        }

        if (! is_array($pair) || count($pair) !== 2) {
            return null;
        }

        $values = array_values($pair);

        return new LeaguePairInputData(
            new LeagueParticipantInputData((int) $values[0], null, null),
            new LeagueParticipantInputData((int) $values[1], null, null),
        );
    }

    private function isValidPlayer(LeagueParticipantInputData $input): bool
    {
        return $input->userId !== null || $this->isValidGuest($input);
    }

    private function isValidGuest(LeagueParticipantInputData $input): bool
    {
        return trim((string) $input->firstName) !== '' && trim((string) $input->lastName) !== '';
    }
}
