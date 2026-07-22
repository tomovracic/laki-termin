<?php

declare(strict_types=1);

namespace App\Http\Requests\Users;

use App\Services\Groups\UserGroupPermissionResolver;
use Illuminate\Foundation\Http\FormRequest;

class SearchUsersRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return $user !== null
            && app(UserGroupPermissionResolver::class)->canAccessMatchHistory($user);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'q' => ['required', 'string', 'min:2', 'max:100'],
        ];
    }
}
