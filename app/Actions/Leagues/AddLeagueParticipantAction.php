<?php

declare(strict_types=1);

namespace App\Actions\Leagues;

use App\DTO\Leagues\AddLeagueParticipantData;
use App\DTO\Leagues\LeagueParticipantInputData;
use App\Models\League;
use App\Models\LeagueParticipant;
use App\Models\User;
use App\Services\Leagues\LeagueMatchGeneratorService;
use App\Services\Leagues\LeagueScheduleService;
use Illuminate\Database\DatabaseManager;
use Illuminate\Validation\ValidationException;

class AddLeagueParticipantAction
{
    public function __construct(
        protected DatabaseManager $database,
        protected LeagueMatchGeneratorService $matchGenerator,
        protected LeagueScheduleService $scheduleService,
    ) {}

    public function execute(AddLeagueParticipantData $data): LeagueParticipant
    {
        $league = League::query()->findOrFail($data->leagueId);

        if ($league->isKnockout() || $league->isGroupKnockout()) {
            throw ValidationException::withMessages([
                'user_id' => ['Sudionici se ne mogu dodavati u turnir nakon kreiranja.'],
            ]);
        }

        $primary = new LeagueParticipantInputData(
            userId: $data->userId,
            firstName: $data->firstName,
            lastName: $data->lastName,
        );

        if ($league->isDoubles()) {
            if ($data->partner === null) {
                throw ValidationException::withMessages([
                    'partner' => ['Par mora imati dva igraca.'],
                ]);
            }

            $this->assertValidPlayer($primary, 'user_id');
            $this->assertValidPlayer($data->partner, 'partner');
            $this->assertUsersExist([$primary->userId, $data->partner->userId]);
            $this->assertUsersNotAlreadyInLeague($league, [$primary->userId, $data->partner->userId]);

            if ($primary->userId !== null && $primary->userId === $data->partner->userId) {
                throw ValidationException::withMessages([
                    'partner' => ['Par mora imati dva razlicita igraca.'],
                ]);
            }
        } else {
            $this->assertValidPlayer($primary, 'user_id');
            $this->assertUsersExist([$primary->userId]);
            $this->assertUsersNotAlreadyInLeague($league, [$primary->userId]);
        }

        return $this->database->transaction(function () use ($league, $primary, $data): LeagueParticipant {
            $existingParticipants = LeagueParticipant::query()
                ->where('league_id', $league->id)
                ->get();

            $participant = LeagueParticipant::query()->create([
                'league_id' => $league->id,
                'user_id' => $primary->userId,
                'first_name' => $primary->userId === null ? trim((string) $primary->firstName) : null,
                'last_name' => $primary->userId === null ? trim((string) $primary->lastName) : null,
                'partner_user_id' => $data->partner?->userId,
                'partner_first_name' => $data->partner !== null && $data->partner->userId === null
                    ? trim((string) $data->partner->firstName)
                    : null,
                'partner_last_name' => $data->partner !== null && $data->partner->userId === null
                    ? trim((string) $data->partner->lastName)
                    : null,
            ]);

            $this->matchGenerator->generateForNewParticipant($league, $participant, $existingParticipants);
            $this->scheduleService->synchronize($league);

            return $participant->load(['user', 'partner']);
        });
    }

    private function assertValidPlayer(LeagueParticipantInputData $input, string $field): void
    {
        if ($input->userId !== null) {
            return;
        }

        if (trim((string) $input->firstName) === '' || trim((string) $input->lastName) === '') {
            throw ValidationException::withMessages([
                $field => ['Gost mora imati ime i prezime.'],
            ]);
        }
    }

    /**
     * @param  list<int|null>  $userIds
     */
    private function assertUsersExist(array $userIds): void
    {
        $ids = array_values(array_filter($userIds, fn (?int $id): bool => $id !== null));

        if ($ids === []) {
            return;
        }

        $existingCount = User::query()->whereIn('id', $ids)->count();

        if ($existingCount !== count(array_unique($ids))) {
            throw ValidationException::withMessages([
                'user_id' => ['Korisnik ne postoji.'],
            ]);
        }
    }

    /**
     * @param  list<int|null>  $userIds
     */
    private function assertUsersNotAlreadyInLeague(League $league, array $userIds): void
    {
        $ids = array_values(array_filter($userIds, fn (?int $id): bool => $id !== null));

        if ($ids === []) {
            return;
        }

        $alreadyParticipant = LeagueParticipant::query()
            ->where('league_id', $league->id)
            ->where(function ($query) use ($ids): void {
                $query->whereIn('user_id', $ids)
                    ->orWhereIn('partner_user_id', $ids);
            })
            ->exists();

        if ($alreadyParticipant) {
            throw ValidationException::withMessages([
                'user_id' => ['Korisnik je vec sudionik ove lige.'],
            ]);
        }
    }
}
