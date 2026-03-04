<?php

declare(strict_types=1);

namespace App\Http\Requests\Terrains;

use App\Models\Terrain;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateTerrainRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var Terrain $terrain */
        $terrain = $this->route('terrain');

        return $this->user()?->can('update', $terrain) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        /** @var Terrain $terrain */
        $terrain = $this->route('terrain');

        return [
            'name' => ['sometimes', 'string', 'max:255'],
            'code' => [
                'sometimes',
                'string',
                'max:100',
                Rule::unique('terrains', 'code')->ignore($terrain->id),
            ],
            'location' => ['sometimes', 'nullable', 'string', 'max:255'],
            'description' => ['sometimes', 'nullable', 'string'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
