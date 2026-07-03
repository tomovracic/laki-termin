<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class IndexAdminReservationReportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'from_date' => ['nullable', 'date_format:Y-m-d'],
            'to_date' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:from_date'],
            'user_id' => ['nullable', 'integer', 'exists:users,id'],
            'terrain_id' => ['nullable', 'integer', 'exists:terrains,id'],
            'period' => ['nullable', 'string', Rule::in(['all', 'upcoming', 'past'])],
            'page' => ['nullable', 'integer', 'min:1'],
        ];
    }
}
