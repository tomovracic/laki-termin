<?php

declare(strict_types=1);

namespace App\Http\Requests\Leagues;

use App\Enums\KnockoutDrawMode;
use App\Enums\LeagueFormat;
use App\Models\League;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

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

        if ($format === LeagueFormat::Knockout->value) {
            return [
                'name' => ['required', 'string', 'max:255'],
                'format' => ['required', Rule::enum(LeagueFormat::class)],
                'sets_best_of' => ['required', 'integer', Rule::in([1, 3, 5])],
                'knockout_draw_mode' => ['nullable', Rule::enum(KnockoutDrawMode::class)],
                'participant_ids' => ['required', 'array', 'min:2'],
                'participant_ids.*' => ['required', 'integer', 'distinct', 'exists:users,id'],
            ];
        }

        return [
            'name' => ['required', 'string', 'max:255'],
            'format' => ['nullable', Rule::enum(LeagueFormat::class)],
            'rounds' => ['required', 'integer', 'min:1', 'max:5'],
            'sets_best_of' => ['nullable', 'integer', Rule::in([1, 3, 5])],
            'participant_ids' => ['required', 'array', 'min:2'],
            'participant_ids.*' => ['required', 'integer', 'distinct', 'exists:users,id'],
        ];
    }
}
