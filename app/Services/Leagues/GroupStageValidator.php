<?php

declare(strict_types=1);

namespace App\Services\Leagues;

use App\Models\LeagueGroup;
use Illuminate\Validation\ValidationException;

class GroupStageValidator
{
    /**
     * @param  list<array{name: string, participant_indexes: list<int>}>  $groups
     */
    public function validate(
        int $participantCount,
        array $groups,
        int $qualifyPerGroup,
        int $bestRunnersUp,
    ): void {
        if (! in_array($qualifyPerGroup, [1, 2], true)) {
            throw ValidationException::withMessages([
                'qualify_per_group' => ['Iz svake skupine moze ici samo prvi ili prva dva igraca.'],
            ]);
        }

        if ($bestRunnersUp < 0) {
            throw ValidationException::withMessages([
                'best_runners_up' => ['Broj najboljih preostalih igraca ne moze biti negativan.'],
            ]);
        }

        $groupCount = count($groups);

        if ($groupCount < 2) {
            throw ValidationException::withMessages([
                'groups' => ['Turnir mora imati najmanje dvije skupine.'],
            ]);
        }

        if ($bestRunnersUp > $groupCount) {
            throw ValidationException::withMessages([
                'best_runners_up' => ['Broj najboljih preostalih ne moze biti veci od broja skupina.'],
            ]);
        }

        if ($participantCount < 4) {
            throw ValidationException::withMessages([
                'participants' => ['Grupni turnir mora imati najmanje cetiri sudionika.'],
            ]);
        }

        $seenIndexes = [];
        $minPlayersPerGroup = $this->minPlayersPerGroup($qualifyPerGroup, $bestRunnersUp);

        foreach ($groups as $groupIndex => $group) {
            $indexes = $group['participant_indexes'];
            $size = count($indexes);

            if ($size < $minPlayersPerGroup) {
                throw ValidationException::withMessages([
                    "groups.{$groupIndex}" => ["Svaka skupina mora imati najmanje {$minPlayersPerGroup} igraca za odabrana pravila kvalifikacije."],
                ]);
            }

            foreach ($indexes as $participantIndex) {
                if ($participantIndex < 0 || $participantIndex >= $participantCount) {
                    throw ValidationException::withMessages([
                        "groups.{$groupIndex}" => ['Skupina sadrzi nepostojeceg igraca.'],
                    ]);
                }

                if (in_array($participantIndex, $seenIndexes, true)) {
                    throw ValidationException::withMessages([
                        'groups' => ['Isti igrac ne moze biti u vise skupina.'],
                    ]);
                }

                $seenIndexes[] = $participantIndex;
            }
        }

        if (count($seenIndexes) !== $participantCount) {
            throw ValidationException::withMessages([
                'groups' => ['Svi igraci moraju biti rasporedeni u skupine.'],
            ]);
        }

        $knockoutSlots = $this->knockoutSlots($groupCount, $qualifyPerGroup, $bestRunnersUp);

        if ($knockoutSlots < 2) {
            throw ValidationException::withMessages([
                'qualify_per_group' => ['U knockout mora ici najmanje dva igraca.'],
            ]);
        }
    }

    public function knockoutSlots(int $groupCount, int $qualifyPerGroup, int $bestRunnersUp): int
    {
        return ($groupCount * $qualifyPerGroup) + $bestRunnersUp;
    }

    public function minPlayersPerGroup(int $qualifyPerGroup, int $bestRunnersUp): int
    {
        $minimum = $qualifyPerGroup + ($bestRunnersUp > 0 ? 1 : 0);

        return max(2, $minimum);
    }

    /**
     * @param  list<LeagueGroup>  $groups
     */
    public function validatePersistedGroups(
        array $groups,
        int $qualifyPerGroup,
        int $bestRunnersUp,
    ): void {
        $payload = [];
        $participants = [];
        $index = 0;

        foreach ($groups as $group) {
            $indexes = [];

            foreach ($group->participants as $participant) {
                $participants[] = $participant;
                $indexes[] = $index;
                $index++;
            }

            $payload[] = [
                'name' => $group->name,
                'participant_indexes' => $indexes,
            ];
        }

        $this->validate($index, $payload, $qualifyPerGroup, $bestRunnersUp);
    }
}
