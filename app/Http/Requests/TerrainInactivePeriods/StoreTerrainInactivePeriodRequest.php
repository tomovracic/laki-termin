<?php

declare(strict_types=1);

namespace App\Http\Requests\TerrainInactivePeriods;

use App\Enums\InactivePeriodBlockType;
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

    private const TIME_FORMAT = 'H:i';

    public function authorize(): bool
    {
        return $this->user()?->can('create', TerrainInactivePeriod::class) ?? false;
    }

    protected function prepareForValidation(): void
    {
        if (! $this->filled('block_type')) {
            $this->merge(['block_type' => InactivePeriodBlockType::FullDay->value]);
        }

        $fromDate = $this->input('from_date');
        $blockType = $this->input('block_type', InactivePeriodBlockType::FullDay->value);

        if (! is_string($fromDate) || $fromDate === '') {
            return;
        }

        if ($blockType === InactivePeriodBlockType::TimeRange->value) {
            $fromTime = $this->input('from_time');
            $toTime = $this->input('to_time');

            if (! is_string($fromTime) || $fromTime === '' || ! is_string($toTime) || $toTime === '') {
                return;
            }

            $fromAt = CarbonImmutable::createFromFormat(
                'Y-m-d '.self::TIME_FORMAT,
                "{$fromDate} {$fromTime}",
                self::BUSINESS_TIMEZONE,
            );
            $toAt = CarbonImmutable::createFromFormat(
                'Y-m-d '.self::TIME_FORMAT,
                "{$fromDate} {$toTime}",
                self::BUSINESS_TIMEZONE,
            );

            $this->merge([
                'to_date' => $fromDate,
                'from_at' => $fromAt->toDateTimeString(),
                'to_at' => $toAt->toDateTimeString(),
            ]);

            return;
        }

        $toDate = $this->input('to_date', $fromDate);

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
            'block_type' => ['required', Rule::enum(InactivePeriodBlockType::class)],
            'from_date' => ['required', 'date_format:Y-m-d'],
            'to_date' => ['required', 'date_format:Y-m-d', 'after_or_equal:from_date'],
            'from_time' => [
                Rule::requiredIf(fn (): bool => $this->input('block_type') === InactivePeriodBlockType::TimeRange->value),
                'nullable',
                'date_format:'.self::TIME_FORMAT,
            ],
            'to_time' => [
                Rule::requiredIf(fn (): bool => $this->input('block_type') === InactivePeriodBlockType::TimeRange->value),
                'nullable',
                'date_format:'.self::TIME_FORMAT,
            ],
            'from_at' => ['required', 'date'],
            'to_at' => ['required', 'date', 'after:from_at'],
            'reason' => ['required', Rule::enum(InactivePeriodReason::class)],
            'note' => ['nullable', 'string', 'max:1000'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $blockType = $this->input('block_type');
            $fromDate = $this->input('from_date');
            $toDate = $this->input('to_date');

            if (! is_string($fromDate) || ! is_string($toDate)) {
                return;
            }

            if ($blockType === InactivePeriodBlockType::TimeRange->value) {
                if ($fromDate !== $toDate) {
                    $validator->errors()->add(
                        'to_date',
                        'Time range blocks must use a single date.',
                    );
                }

                $fromTime = $this->input('from_time');
                $toTime = $this->input('to_time');

                if (is_string($fromTime) && is_string($toTime) && $fromTime >= $toTime) {
                    $validator->errors()->add(
                        'to_time',
                        'The end time must be after the start time.',
                    );
                }

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

        unset($validated['from_date'], $validated['to_date'], $validated['block_type'], $validated['from_time'], $validated['to_time']);

        return $validated;
    }
}
