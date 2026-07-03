<?php

declare(strict_types=1);

namespace App\Http\Requests\AppSettings;

use App\Enums\TerrainUsageRuleEmphasis;
use App\Enums\TerrainUsageRuleIcon;
use App\Models\User;
use App\Services\AppSettingService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class SaveTerrainUsageRuleRequest extends FormRequest
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
            'icon' => ['required', Rule::enum(TerrainUsageRuleIcon::class)],
            'text' => ['required', 'string', 'max:500'],
            'emphasis' => ['nullable', Rule::enum(TerrainUsageRuleEmphasis::class)],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        if (! $this->isMethod('POST')) {
            return;
        }

        $validator->after(function (Validator $validator): void {
            /** @var AppSettingService $appSettingService */
            $appSettingService = app(AppSettingService::class);

            if (count($appSettingService->getTerrainUsageRules()) >= 20) {
                $validator->errors()->add('text', __('validation.max.array', [
                    'attribute' => 'rules',
                    'max' => 20,
                ]));
            }
        });
    }
}
