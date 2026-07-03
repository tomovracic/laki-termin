<?php

declare(strict_types=1);

namespace App\Http\Requests\TerrainInactivePeriods;

use App\Enums\InactivePeriodReason;
use App\Models\TerrainInactivePeriod;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreTerrainInactivePeriodRequest extends FormRequest
{
    private const BUSINESS_TIMEZONE = 'Europe/Zagreb';

    private const MAX_DATE_RANGE_DAYS = 90;

    public function authorize(): bool
    {
        return $this->user()?->can('create', TerrainInactivePeriod::class) ?? false;
    }

    protected function prepareForValidation(): void
    {
        $fromDate = $this->input('from_date');
        $toDate = $this->input('to_date', $fromDate);

        if (! is_string($fromDate) || $fromDate === '') {
            return;
        }

        if (! is_string($toDate) || $toDate === '') {
            $toDate = $fromDate;
        }

        $fromAt = CarbonImmutable::createFromFormat('Y-m-d', $fromDate, self::BUSINESS_TIMEZONE)
            ->startOfDay();
        $toAt = CarbonImmutable::createFromFormat('Y-m-d', $toDate, self::BUSINESS_TIMEZONE)
            ->endOfDay();

        $this->merge([
            'to_date' => $toDate,
            'from_at' => $fromAt->toDateTimeString(),
            'to_at' => $toAt->toDateTimeString(),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'terrain_id' => ['nullable', 'integer', 'exists:terrains,id'],
            'from_date' => ['required', 'date_format:Y-m-d'],
            'to_date' => ['required', 'date_format:Y-m-d', 'after_or_equal:from_date'],
            'from_at' => ['required', 'date'],
            'to_at' => ['required', 'date', 'after:from_at'],
            'reason' => ['required', Rule::enum(InactivePeriodReason::class)],
            'note' => ['nullable', 'string', 'max:1000'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $fromDate = $this->input('from_date');
            $toDate = $this->input('to_date');

            if (! is_string($fromDate) || ! is_string($toDate)) {
                return;
            }

            $from = CarbonImmutable::createFromFormat('Y-m-d', $fromDate, self::BUSINESS_TIMEZONE);
            $to = CarbonImmutable::createFromFormat('Y-m-d', $toDate, self::BUSINESS_TIMEZONE);

            if ($from->diffInDays($to) > self::MAX_DATE_RANGE_DAYS) {
                $validator->errors()->add(
                    'to_date',
                    'The date range may not exceed '.self::MAX_DATE_RANGE_DAYS.' days.',
                );
            }
        });
    }

    /**
     * @return array<string, mixed>
     */
    public function validated($key = null, $default = null): mixed
    {
        $validated = parent::validated($key, $default);

        if ($key !== null) {
            return $validated;
        }

        unset($validated['from_date'], $validated['to_date']);

        return $validated;
    }
}
