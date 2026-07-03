<?php

declare(strict_types=1);

namespace App\Http\Requests\Leagues;

use App\Models\League;
use Illuminate\Foundation\Http\FormRequest;

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
        return [
            'name' => ['required', 'string', 'max:255'],
            'rounds' => ['required', 'integer', 'min:1', 'max:5'],
            'participant_ids' => ['required', 'array', 'min:2'],
            'participant_ids.*' => ['required', 'integer', 'distinct', 'exists:users,id'],
        ];
    }
}
