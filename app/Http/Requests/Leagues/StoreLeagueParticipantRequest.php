<?php

declare(strict_types=1);

namespace App\Http\Requests\Leagues;

use App\DTO\Leagues\AddLeagueParticipantData;
use App\DTO\Leagues\LeagueParticipantInputData;
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
            'user_id' => ['nullable', 'integer', 'exists:users,id'],
            'first_name' => ['nullable', 'string', 'max:255'],
            'last_name' => ['nullable', 'string', 'max:255'],
            'partner' => ['nullable', 'array'],
            'partner.user_id' => ['nullable', 'integer', 'exists:users,id'],
            'partner.first_name' => ['nullable', 'string', 'max:255'],
            'partner.last_name' => ['nullable', 'string', 'max:255'],
        ];
    }

    public function toAddLeagueParticipantData(League $league): AddLeagueParticipantData
    {
        $validated = $this->validated();
        $userId = isset($validated['user_id']) ? (int) $validated['user_id'] : 0;
        $partner = null;

        if (isset($validated['partner']) && is_array($validated['partner'])) {
            $partnerUserId = isset($validated['partner']['user_id']) ? (int) $validated['partner']['user_id'] : 0;
            $partner = new LeagueParticipantInputData(
                userId: $partnerUserId > 0 ? $partnerUserId : null,
                firstName: isset($validated['partner']['first_name']) ? trim((string) $validated['partner']['first_name']) : null,
                lastName: isset($validated['partner']['last_name']) ? trim((string) $validated['partner']['last_name']) : null,
            );
        }

        return new AddLeagueParticipantData(
            leagueId: $league->id,
            userId: $userId > 0 ? $userId : null,
            firstName: isset($validated['first_name']) ? trim((string) $validated['first_name']) : null,
            lastName: isset($validated['last_name']) ? trim((string) $validated['last_name']) : null,
            partner: $partner,
        );
    }
}
