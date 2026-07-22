<?php

declare(strict_types=1);

namespace App\Http\Requests\Groups;

use App\Enums\GroupColor;
use App\Models\Group;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateGroupRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var Group $group */
        $group = $this->route('group');

        return $this->user()?->can('update', $group) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        /** @var Group $group */
        $group = $this->route('group');

        return [
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('groups', 'name')->ignore($group->id),
            ],
            'color' => ['required', 'string', Rule::enum(GroupColor::class)],
            'can_access_ranking' => ['required', 'boolean'],
            'can_view_all_ranking_groups' => ['required', 'boolean'],
            'can_access_match_history' => ['required', 'boolean'],
            'can_view_all_match_history_groups' => ['required', 'boolean'],
        ];
    }
}
