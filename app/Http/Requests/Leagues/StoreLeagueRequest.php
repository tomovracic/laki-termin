<?php

declare(strict_types=1);

namespace App\Http\Requests\Leagues;

use App\DTO\Leagues\CreateLeagueData;
use App\DTO\Leagues\LeagueGroupInputData;
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

        if ($format === LeagueFormat::Knockout->value && $participantMode === LeagueParticipantMode::Doubles->value) {
            return [
                'name' => ['required', 'string', 'max:255'],
                'format' => ['required', Rule::enum(LeagueFormat::class)],
                'participant_mode' => ['required', Rule::enum(LeagueParticipantMode::class)],
                'sets_best_of' => ['required', 'integer', Rule::in([1, 3, 5])],
                'knockout_draw_mode' => ['nullable', Rule::enum(KnockoutDrawMode::class)],
                'pairs' => ['required', 'array', 'min:2'],
                'pairs.*' => ['required', 'array', 'size:2'],
                'pairs.*.*' => ['required', 'integer', 'exists:users,id'],
            ];
        }

        if ($format === LeagueFormat::Knockout->value) {
            return [
                'name' => ['required', 'string', 'max:255'],
                'format' => ['required', Rule::enum(LeagueFormat::class)],
                'participant_mode' => ['nullable', Rule::enum(LeagueParticipantMode::class)],
                'sets_best_of' => ['required', 'integer', Rule::in([1, 3, 5])],
                'knockout_draw_mode' => ['nullable', Rule::enum(KnockoutDrawMode::class)],
                ...$this->singlesParticipantRules(),
            ];
        }

        if ($format === LeagueFormat::GroupKnockout->value) {
            return [
                'name' => ['required', 'string', 'max:255'],
                'format' => ['required', Rule::enum(LeagueFormat::class)],
                'participant_mode' => ['nullable', Rule::enum(LeagueParticipantMode::class)],
                'sets_best_of' => ['required', 'integer', Rule::in([1, 3, 5])],
                'knockout_draw_mode' => ['nullable', Rule::enum(KnockoutDrawMode::class)],
                'qualify_per_group' => ['required', 'integer', Rule::in([1, 2])],
                'best_runners_up' => ['required', 'integer', 'min:0'],
                'participants' => ['required', 'array', 'min:4'],
                'participants.*.user_id' => ['nullable', 'integer', 'exists:users,id'],
                'participants.*.first_name' => ['nullable', 'string', 'max:255'],
                'participants.*.last_name' => ['nullable', 'string', 'max:255'],
                'groups' => ['required', 'array', 'min:2'],
                'groups.*.name' => ['required', 'string', 'max:32'],
                'groups.*.participant_indexes' => ['required', 'array', 'min:2'],
                'groups.*.participant_indexes.*' => ['required', 'integer', 'min:0'],
            ];
        }

        return [
            'name' => ['required', 'string', 'max:255'],
            'format' => ['nullable', Rule::enum(LeagueFormat::class)],
            'participant_mode' => ['nullable', Rule::enum(LeagueParticipantMode::class)],
            'rounds' => ['required', 'integer', 'min:1', 'max:5'],
            'sets_best_of' => ['nullable', 'integer', Rule::in([1, 3, 5])],
            'participant_ids' => ['required', 'array', 'min:2'],
            'participant_ids.*' => ['required', 'integer', 'distinct', 'exists:users,id'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $format = $this->input('format', LeagueFormat::RoundRobin->value);
            $participantMode = $this->input('participant_mode', LeagueParticipantMode::Singles->value);

            if ($participantMode === LeagueParticipantMode::Doubles->value
                && $format !== LeagueFormat::Knockout->value) {
                $validator->errors()->add(
                    'participant_mode',
                    'Parovi su dozvoljeni samo za knockout turnir.',
                );

                return;
            }

            if ($format === LeagueFormat::Knockout->value
                && $participantMode !== LeagueParticipantMode::Doubles->value) {
                $this->validateSinglesParticipants($validator);
            }

            if ($format === LeagueFormat::GroupKnockout->value) {
                $this->validateSinglesParticipants($validator);
                $this->validateGroupStage($validator);
            }

            if ($participantMode !== LeagueParticipantMode::Doubles->value) {
                return;
            }

            $this->validatePairs($validator);
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
            pairs: $this->parsePairs($validated['pairs'] ?? []),
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

            $userId = isset($participant['user_id']) ? (int) $participant['user_id'] : 0;
            $firstName = trim((string) ($participant['first_name'] ?? ''));
            $lastName = trim((string) ($participant['last_name'] ?? ''));

            if ($userId > 0) {
                if (in_array($userId, $seenUserIds, true)) {
                    $validator->errors()->add(
                        'participants',
                        'Isti korisnik ne moze biti dodan vise puta.',
                    );

                    return;
                }

                $seenUserIds[] = $userId;

                continue;
            }

            if ($firstName === '' || $lastName === '') {
                $validator->errors()->add(
                    "participants.{$index}",
                    'Gost mora imati ime i prezime.',
                );
            }
        }
    }

    private function validateGroupStage(Validator $validator): void
    {
        $participants = $this->input('participants', []);
        $groups = $this->input('groups', []);

        if (! is_array($participants) || ! is_array($groups)) {
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
                count($participants),
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
            if (! is_array($pair) || count($pair) !== 2) {
                continue;
            }

            $firstId = (int) ($pair[0] ?? 0);
            $secondId = (int) ($pair[1] ?? 0);

            if ($firstId === $secondId) {
                $validator->errors()->add(
                    "pairs.{$index}",
                    'Par mora imati dva razlicita igraca.',
                );
            }

            foreach ([$firstId, $secondId] as $userId) {
                if ($userId < 1) {
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
     * @return list<array{0: int, 1: int}>
     */
    private function parsePairs(array $pairs): array
    {
        $parsed = [];

        foreach ($pairs as $pair) {
            if (! is_array($pair)) {
                continue;
            }

            $values = array_values($pair);

            if (count($values) !== 2) {
                continue;
            }

            $parsed[] = [(int) $values[0], (int) $values[1]];
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

            $userId = isset($participant['user_id']) ? (int) $participant['user_id'] : 0;

            $parsed[] = new LeagueParticipantInputData(
                userId: $userId > 0 ? $userId : null,
                firstName: isset($participant['first_name']) ? trim((string) $participant['first_name']) : null,
                lastName: isset($participant['last_name']) ? trim((string) $participant['last_name']) : null,
            );
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
}
