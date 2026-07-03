<?php

declare(strict_types=1);

namespace App\Http\Requests\AppSettings;

use App\Enums\TerrainUsageRuleIcon;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateTerrainUsageRulesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('viewAny', User::class) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'rules' => ['present', 'array', 'max:20'],
            'rules.*.icon' => ['required', Rule::enum(TerrainUsageRuleIcon::class)],
            'rules.*.text' => ['required', 'string', 'max:500'],
        ];
    }
}
