<?php

declare(strict_types=1);

namespace App\Http\Requests\Leagues;

use App\Enums\KnockoutDrawMode;
use App\Enums\LeagueFormat;
use App\Enums\LeagueParticipantMode;
use App\Models\League;
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
                'participant_ids' => ['required', 'array', 'min:2'],
                'participant_ids.*' => ['required', 'integer', 'distinct', 'exists:users,id'],
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

            if ($participantMode !== LeagueParticipantMode::Doubles->value) {
                return;
            }

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
        });
    }
}
