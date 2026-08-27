<?php

declare(strict_types=1);

namespace App\Http\Requests\Leagues;

use App\Models\League;
use Illuminate\Foundation\Http\FormRequest;

class StartKnockoutFromGroupsRequest extends FormRequest
{
    public function authorize(): bool
    {
        $league = $this->route('league');

        return $league instanceof League
            && ($this->user()?->can('finishRound', $league) ?? false);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [];
    }
}
