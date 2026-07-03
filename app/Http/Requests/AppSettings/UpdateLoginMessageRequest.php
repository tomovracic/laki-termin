<?php

declare(strict_types=1);

namespace App\Http\Requests\AppSettings;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;

class UpdateLoginMessageRequest extends FormRequest
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
            'login_message' => ['nullable', 'string', 'max:5000'],
        ];
    }
}
