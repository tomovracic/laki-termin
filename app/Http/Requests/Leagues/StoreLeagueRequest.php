<?php

declare(strict_types=1);

namespace App\Http\Requests\Leagues;

use App\DTO\Leagues\CreateLeagueData;
use App\DTO\Leagues\LeagueGroupInputData;
use App\DTO\Leagues\LeaguePairInputData;
use App\DTO\Leagues\LeagueParticipantInputData;
use App\Enums\KnockoutDrawMode;
use App\Enums\LeagueFormat;
use App\Enums\LeagueParticipantMode;
use App\Models\League;
use App\Services\Leagues\GroupStageValidator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreLeagueRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', League::class) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $format = $this->input('format', LeagueFormat::RoundRobin->value);
        $participantMode = $this->input('participant_mode', LeagueParticipantMode::Singles->value);
        $isDoubles = $participantMode === LeagueParticipantMode::Doubles->value;
        $isGroupKnockout = $format === LeagueFormat::GroupKnockout->value;
        $isKnockout = $format === LeagueFormat::Knockout->value;
        $isTournament = $isKnockout || $isGroupKnockout;

        $rules = [
            'name' => ['required', 'string', 'max:255'],
            'format' => [$isTournament ? 'required' : 'nullable', Rule::enum(LeagueFormat::class)],
            'participant_mode' => ['nullable', Rule::enum(LeagueParticipantMode::class)],
            'sets_best_of' => [$isTournament ? 'required' : 'nullable', 'integer', Rule::in([1, 3, 5])],
            'knockout_draw_mode' => ['nullable', Rule::enum(KnockoutDrawMode::class)],
        ];

        if (! $isTournament) {
            $rules['rounds'] = ['required', 'integer', 'min:1', 'max:5'];
        }

        if ($isGroupKnockout) {
            $rules['qualify_per_group'] = ['required', 'integer', Rule::in([1, 2])];
            $rules['best_runners_up'] = ['required', 'integer', 'min:0'];
            $rules['groups'] = ['required', 'array', 'min:2'];
            $rules['groups.*.name'] = ['required', 'string', 'max:32'];
            $rules['groups.*.participant_indexes'] = ['required', 'array', 'min:2'];
            $rules['groups.*.participant_indexes.*'] = ['required', 'integer', 'min:0'];
        }

        if ($isDoubles) {
            $minPairs = $isGroupKnockout ? 4 : 2;

            return [
                ...$rules,
                'pairs' => ['required', 'array', 'min:'.$minPairs],
                'pairs.*' => ['required', 'array'],
                'pairs.*.player_one' => ['nullable', 'array'],
                'pairs.*.player_one.user_id' => ['nullable', 'integer', 'exists:users,id'],
                'pairs.*.player_one.first_name' => ['nullable', 'string', 'max:255'],
                'pairs.*.player_one.last_name' => ['nullable', 'string', 'max:255'],
                'pairs.*.player_two' => ['nullable', 'array'],
                'pairs.*.player_two.user_id' => ['nullable', 'integer', 'exists:users,id'],
                'pairs.*.player_two.first_name' => ['nullable', 'string', 'max:255'],
                'pairs.*.player_two.last_name' => ['nullable', 'string', 'max:255'],
                'pairs.*.0' => ['nullable', 'integer', 'exists:users,id'],
                'pairs.*.1' => ['nullable', 'integer', 'exists:users,id'],
            ];
        }

        if ($isGroupKnockout) {
            return [
                ...$rules,
                'participants' => ['required', 'array', 'min:4'],
                'participants.*.user_id' => ['nullable', 'integer', 'exists:users,id'],
                'participants.*.first_name' => ['nullable', 'string', 'max:255'],
                'participants.*.last_name' => ['nullable', 'string', 'max:255'],
            ];
        }

        return [...$rules, ...$this->singlesParticipantRules()];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $format = $this->input('format', LeagueFormat::RoundRobin->value);
            $participantMode = $this->input('participant_mode', LeagueParticipantMode::Singles->value);

            if ($participantMode === LeagueParticipantMode::Doubles->value) {
                $this->validatePairs($validator);
            } else {
                $this->validateSinglesParticipants($validator);
            }

            if ($format === LeagueFormat::GroupKnockout->value) {
                $this->validateGroupStage($validator);
            }
        });
    }

    public function toCreateLeagueData(): CreateLeagueData
    {
        $validated = $this->validated();
        $format = LeagueFormat::tryFrom((string) ($validated['format'] ?? LeagueFormat::RoundRobin->value))
            ?? LeagueFormat::RoundRobin;
        $drawMode = KnockoutDrawMode::tryFrom((string) ($validated['knockout_draw_mode'] ?? KnockoutDrawMode::Seeded->value))
            ?? KnockoutDrawMode::Seeded;
        $participantMode = LeagueParticipantMode::tryFrom((string) ($validated['participant_mode'] ?? LeagueParticipantMode::Singles->value))
            ?? LeagueParticipantMode::Singles;

        return new CreateLeagueData(
            name: (string) $validated['name'],
            rounds: (int) ($validated['rounds'] ?? 1),
            createdBy: $this->user()->id,
            participantIds: array_map('intval', $validated['participant_ids'] ?? []),
            format: $format,
            participantMode: $participantMode,
            setsBestOf: (int) ($validated['sets_best_of'] ?? 3),
            knockoutDrawMode: $drawMode,
            pairs: $this->parsePairs($this->input('pairs', [])),
            participants: $this->parseParticipants($validated['participants'] ?? []),
            qualifyPerGroup: (int) ($validated['qualify_per_group'] ?? 1),
            bestRunnersUp: (int) ($validated['best_runners_up'] ?? 0),
            groups: $this->parseGroups($validated['groups'] ?? []),
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function singlesParticipantRules(): array
    {
        if ($this->has('participants')) {
            return [
                'participants' => ['required', 'array', 'min:2'],
                'participants.*.user_id' => ['nullable', 'integer', 'exists:users,id'],
                'participants.*.first_name' => ['nullable', 'string', 'max:255'],
                'participants.*.last_name' => ['nullable', 'string', 'max:255'],
            ];
        }

        return [
            'participant_ids' => ['required', 'array', 'min:2'],
            'participant_ids.*' => ['required', 'integer', 'distinct', 'exists:users,id'],
        ];
    }

    private function validateSinglesParticipants(Validator $validator): void
    {
        $participants = $this->input('participants');

        if (! is_array($participants)) {
            return;
        }

        $seenUserIds = [];

        foreach ($participants as $index => $participant) {
            if (! is_array($participant)) {
                continue;
            }

            $input = $this->playerInputFromArray($participant);

            if ($input->userId !== null) {
                if (in_array($input->userId, $seenUserIds, true)) {
                    $validator->errors()->add(
                        'participants',
                        'Isti korisnik ne moze biti dodan vise puta.',
                    );

                    return;
                }

                $seenUserIds[] = $input->userId;

                continue;
            }

            if (! $this->isValidGuest($input)) {
                $validator->errors()->add(
                    "participants.{$index}",
                    'Gost mora imati ime i prezime.',
                );
            }
        }
    }

    private function validateGroupStage(Validator $validator): void
    {
        $participantMode = $this->input('participant_mode', LeagueParticipantMode::Singles->value);
        $entries = $participantMode === LeagueParticipantMode::Doubles->value
            ? $this->input('pairs', [])
            : $this->input('participants', []);
        $groups = $this->input('groups', []);

        if (! is_array($entries) || ! is_array($groups)) {
            return;
        }

        $normalizedGroups = [];

        foreach ($groups as $group) {
            if (! is_array($group)) {
                continue;
            }

            $indexes = array_map('intval', $group['participant_indexes'] ?? []);

            $normalizedGroups[] = [
                'name' => (string) ($group['name'] ?? ''),
                'participant_indexes' => $indexes,
            ];
        }

        try {
            app(GroupStageValidator::class)->validate(
                count($entries),
                $normalizedGroups,
                (int) $this->input('qualify_per_group', 1),
                (int) $this->input('best_runners_up', 0),
            );
        } catch (\Illuminate\Validation\ValidationException $exception) {
            foreach ($exception->errors() as $key => $messages) {
                foreach ($messages as $message) {
                    $validator->errors()->add($key, $message);
                }
            }
        }
    }

    private function validatePairs(Validator $validator): void
    {
        $pairs = $this->input('pairs', []);

        if (! is_array($pairs)) {
            return;
        }

        $seenUserIds = [];

        foreach ($pairs as $index => $pair) {
            $parsed = $this->pairFromInput($pair);

            if ($parsed === null) {
                $validator->errors()->add(
                    "pairs.{$index}",
                    'Svaki par mora imati tocno dva igraca.',
                );

                continue;
            }

            if (! $this->isValidPlayer($parsed->playerOne)) {
                $validator->errors()->add(
                    "pairs.{$index}.player_one",
                    'Gost mora imati ime i prezime.',
                );
            }

            if (! $this->isValidPlayer($parsed->playerTwo)) {
                $validator->errors()->add(
                    "pairs.{$index}.player_two",
                    'Gost mora imati ime i prezime.',
                );
            }

            if (
                $parsed->playerOne->userId !== null
                && $parsed->playerOne->userId === $parsed->playerTwo->userId
            ) {
                $validator->errors()->add(
                    "pairs.{$index}",
                    'Par mora imati dva razlicita igraca.',
                );
            }

            foreach ([$parsed->playerOne->userId, $parsed->playerTwo->userId] as $userId) {
                if ($userId === null) {
                    continue;
                }

                if (in_array($userId, $seenUserIds, true)) {
                    $validator->errors()->add(
                        'pairs',
                        'Isti korisnik ne moze biti u vise parova.',
                    );

                    return;
                }

                $seenUserIds[] = $userId;
            }
        }
    }

    /**
     * @param  list<mixed>  $pairs
     * @return list<LeaguePairInputData>
     */
    private function parsePairs(array $pairs): array
    {
        $parsed = [];

        foreach ($pairs as $pair) {
            $normalized = $this->pairFromInput($pair);

            if ($normalized === null) {
                continue;
            }

            $parsed[] = $normalized;
        }

        return $parsed;
    }

    /**
     * @param  list<mixed>  $participants
     * @return list<LeagueParticipantInputData>
     */
    private function parseParticipants(array $participants): array
    {
        $parsed = [];

        foreach ($participants as $participant) {
            if (! is_array($participant)) {
                continue;
            }

            $parsed[] = $this->playerInputFromArray($participant);
        }

        return $parsed;
    }

    /**
     * @param  list<mixed>  $groups
     * @return list<LeagueGroupInputData>
     */
    private function parseGroups(array $groups): array
    {
        $parsed = [];

        foreach ($groups as $group) {
            if (! is_array($group)) {
                continue;
            }

            $indexes = array_map('intval', $group['participant_indexes'] ?? []);

            $parsed[] = new LeagueGroupInputData(
                name: (string) ($group['name'] ?? ''),
                participantIndexes: $indexes,
            );
        }

        return $parsed;
    }

    private function pairFromInput(mixed $pair): ?LeaguePairInputData
    {
        if (! is_array($pair)) {
            return null;
        }

        if (isset($pair['player_one'], $pair['player_two']) && is_array($pair['player_one']) && is_array($pair['player_two'])) {
            return new LeaguePairInputData(
                $this->playerInputFromArray($pair['player_one']),
                $this->playerInputFromArray($pair['player_two']),
            );
        }

        $values = array_values($pair);

        if (count($values) !== 2) {
            return null;
        }

        if (is_array($values[0]) && is_array($values[1])) {
            return new LeaguePairInputData(
                $this->playerInputFromArray($values[0]),
                $this->playerInputFromArray($values[1]),
            );
        }

        $firstId = (int) $values[0];
        $secondId = (int) $values[1];

        if ($firstId < 1 || $secondId < 1) {
            return null;
        }

        return new LeaguePairInputData(
            new LeagueParticipantInputData($firstId, null, null),
            new LeagueParticipantInputData($secondId, null, null),
        );
    }

    /**
     * @param  array<string, mixed>  $player
     */
    private function playerInputFromArray(array $player): LeagueParticipantInputData
    {
        $userId = isset($player['user_id']) ? (int) $player['user_id'] : 0;

        return new LeagueParticipantInputData(
            userId: $userId > 0 ? $userId : null,
            firstName: isset($player['first_name']) ? trim((string) $player['first_name']) : null,
            lastName: isset($player['last_name']) ? trim((string) $player['last_name']) : null,
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
