<?php

declare(strict_types=1);

namespace App\Http\Requests\AppSettings;

use Illuminate\Foundation\Http\FormRequest;

class AcknowledgeTerrainUsageRulesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [];
    }
}
