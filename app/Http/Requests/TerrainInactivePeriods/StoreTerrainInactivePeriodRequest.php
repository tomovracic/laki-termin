<?php

declare(strict_types=1);

namespace App\Http\Requests\TerrainInactivePeriods;

use App\Models\TerrainInactivePeriod;
use Illuminate\Foundation\Http\FormRequest;

class StoreTerrainInactivePeriodRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', TerrainInactivePeriod::class) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'terrain_id' => ['nullable', 'integer', 'exists:terrains,id'],
            'from_at' => ['required', 'date'],
            'to_at' => ['required', 'date', 'after:from_at'],
            'reason' => ['required', 'string', 'max:255'],
            'note' => ['nullable', 'string'],
        ];
    }
}
