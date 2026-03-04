<?php

declare(strict_types=1);

namespace App\Http\Requests\Reservations;

use App\Models\Reservation;
use Illuminate\Foundation\Http\FormRequest;

class StoreBulkReservationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', Reservation::class) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'reservation_slot_ids' => ['required', 'array', 'min:1'],
            'reservation_slot_ids.*' => ['required', 'integer', 'distinct', 'exists:reservation_slots,id'],
        ];
    }
}
