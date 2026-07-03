<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\UserLoginLog */
class UserLoginLogResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'user_id' => $this->user_id,
            'logged_in_at' => $this->logged_in_at?->toISOString(),
            'ip_address' => $this->ip_address,
            'user' => UserResource::make($this->whenLoaded('user')),
        ];
    }
}
