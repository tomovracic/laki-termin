<?php

declare(strict_types=1);

namespace App\Http\Requests\Groups;

use App\Enums\GroupColor;
use App\Models\Group;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreGroupRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', Group::class) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255', 'unique:groups,name'],
            'color' => ['required', 'string', Rule::enum(GroupColor::class)],
            'can_access_ranking' => ['required', 'boolean'],
            'can_view_all_ranking_groups' => ['required', 'boolean'],
            'can_access_match_history' => ['required', 'boolean'],
            'can_view_all_match_history_groups' => ['required', 'boolean'],
        ];
    }
}
