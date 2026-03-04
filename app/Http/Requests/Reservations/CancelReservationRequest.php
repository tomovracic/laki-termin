<?php

declare(strict_types=1);

namespace App\Http\Requests\Reservations;

use App\Models\Reservation;
use Illuminate\Foundation\Http\FormRequest;

class CancelReservationRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var Reservation $reservation */
        $reservation = $this->route('reservation');

        return $this->user()?->can('cancel', $reservation) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'cancel_reason' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
