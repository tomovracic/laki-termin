<?php

declare(strict_types=1);

namespace App\Http\Requests\Leagues;

use App\Models\League;
use Illuminate\Foundation\Http\FormRequest;

class StoreLeagueParticipantRequest extends FormRequest
{
    public function authorize(): bool
    {
        $league = $this->route('league');

        return $league instanceof League
            && ($this->user()?->can('manageParticipants', $league) ?? false);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'user_id' => ['required', 'integer', 'exists:users,id'],
        ];
    }
}
