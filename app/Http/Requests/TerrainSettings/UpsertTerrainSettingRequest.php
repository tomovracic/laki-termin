<?php

declare(strict_types=1);

namespace App\Http\Requests\TerrainSettings;

use App\Models\Terrain;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpsertTerrainSettingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', Terrain::class) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'terrain_id' => ['prohibited'],
            'is_global' => ['prohibited'],
            'max_advance_days' => ['required', 'integer', 'min:1', 'max:365'],
            'availability_periods' => ['required', 'array', 'min:1'],
            'availability_periods.*.from' => ['required', 'date_format:H:i'],
            'availability_periods.*.to' => ['required', 'date_format:H:i'],
            'availability_periods.*.slot_duration_minutes' => ['required', 'integer', Rule::in([30, 45, 60, 90, 120])],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $periods = $this->input('availability_periods', []);

            if (! is_array($periods)) {
                return;
            }

            $normalizedPeriods = [];

            foreach ($periods as $index => $period) {
                $from = $period['from'] ?? null;
                $to = $period['to'] ?? null;
                $duration = (int) ($period['slot_duration_minutes'] ?? 0);

                if (! is_string($from) || ! is_string($to)) {
                    continue;
                }

                [$fromHour, $fromMinute] = array_map('intval', explode(':', $from));
                [$toHour, $toMinute] = array_map('intval', explode(':', $to));

                $fromTotalMinutes = ($fromHour * 60) + $fromMinute;
                $toTotalMinutes = ($toHour * 60) + $toMinute;

                if ($toTotalMinutes <= $fromTotalMinutes) {
                    $validator->errors()->add(
                        "availability_periods.{$index}.to",
                        'The period end must be after period start.',
                    );

                    continue;
                }

                $periodLength = $toTotalMinutes - $fromTotalMinutes;

                if ($periodLength < $duration) {
                    $validator->errors()->add(
                        "availability_periods.{$index}.slot_duration_minutes",
                        'The slot duration must fit inside the period window.',
                    );
                }

                $normalizedPeriods[] = [
                    'index' => $index,
                    'from' => $fromTotalMinutes,
                    'to' => $toTotalMinutes,
                ];
            }

            usort(
                $normalizedPeriods,
                static fn (array $left, array $right): int => $left['from'] <=> $right['from'],
            );

            for ($index = 1; $index < count($normalizedPeriods); $index++) {
                $previous = $normalizedPeriods[$index - 1];
                $current = $normalizedPeriods[$index];

                if ($current['from'] < $previous['to']) {
                    $validator->errors()->add(
                        "availability_periods.{$current['index']}.from",
                        'Availability periods cannot overlap.',
                    );
                }
            }
        });
    }
}
