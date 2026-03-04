import { Plus, RefreshCcw, Trash2 } from 'lucide-react';
import type { FormEvent } from 'react';
import type { GlobalSetting } from '@/components/admin/types';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { useI18n } from '@/lib/i18n';

type GlobalSettingsFormProps = {
    value: GlobalSetting;
    isSaving: boolean;
    errors: Record<string, string[]>;
    onChange: (value: GlobalSetting) => void;
    onSubmit: (event: FormEvent<HTMLFormElement>) => void;
};

const SLOT_DURATION_OPTIONS = [30, 45, 60] as const;
const HOUR_TIME_OPTIONS = Array.from(
    { length: 24 },
    (_, hour) => `${String(hour).padStart(2, '0')}:00`,
);

function getValidSlotDuration(
    duration: number,
): (typeof SLOT_DURATION_OPTIONS)[number] {
    return SLOT_DURATION_OPTIONS.includes(
        duration as (typeof SLOT_DURATION_OPTIONS)[number],
    )
        ? (duration as (typeof SLOT_DURATION_OPTIONS)[number])
        : 60;
}

function getValidPeriodTime(value: string, fallback: string): string {
    return HOUR_TIME_OPTIONS.includes(value) ? value : fallback;
}

export function GlobalSettingsForm({
    value,
    isSaving,
    errors,
    onChange,
    onSubmit,
}: GlobalSettingsFormProps) {
    const { t } = useI18n();
    const firstError = Object.values(errors)[0]?.[0];

    function updatePeriod(
        periodIndex: number,
        updater: (
            period: GlobalSetting['availability_periods'][number],
        ) => GlobalSetting['availability_periods'][number],
    ): void {
        onChange({
            ...value,
            availability_periods: value.availability_periods.map(
                (period, index) =>
                    index === periodIndex ? updater(period) : period,
            ),
        });
    }

    function addPeriod(): void {
        onChange({
            ...value,
            availability_periods: [
                ...value.availability_periods,
                {
                    from: '08:00',
                    to: '10:00',
                    slot_duration_minutes: 60,
                },
            ],
        });
    }

    function removePeriod(periodIndex: number): void {
        const nextPeriods = value.availability_periods.filter(
            (_, index) => index !== periodIndex,
        );

        onChange({
            ...value,
            availability_periods: nextPeriods,
        });
    }

    return (
        <form className="space-y-3" onSubmit={onSubmit}>
            <div className="grid gap-2">
                <div className="flex items-center justify-between">
                    <Label>{t('availability_periods')}</Label>
                    <Button
                        type="button"
                        variant="outline"
                        size="sm"
                        onClick={addPeriod}
                    >
                        <Plus className="size-4" />
                        {t('add_period')}
                    </Button>
                </div>

                <div className="space-y-3">
                    {value.availability_periods.map((period, index) => (
                        <div
                            key={`period-${index}`}
                            className="space-y-3 rounded-md border border-border/70 p-3"
                        >
                            <div className="grid grid-cols-1 gap-3 md:grid-cols-3">
                                <div className="grid gap-2">
                                    <Label>{t('from')}</Label>
                                    <Select
                                        value={getValidPeriodTime(
                                            period.from,
                                            '08:00',
                                        )}
                                        onValueChange={(nextValue) =>
                                            updatePeriod(index, (current) => ({
                                                ...current,
                                                from: nextValue,
                                            }))
                                        }
                                    >
                                        <SelectTrigger className="w-full">
                                            <SelectValue />
                                        </SelectTrigger>
                                        <SelectContent>
                                            {HOUR_TIME_OPTIONS.map((timeOption) => (
                                                <SelectItem
                                                    key={`from-${timeOption}`}
                                                    value={timeOption}
                                                >
                                                    {timeOption}
                                                </SelectItem>
                                            ))}
                                        </SelectContent>
                                    </Select>
                                </div>
                                <div className="grid gap-2">
                                    <Label>{t('to')}</Label>
                                    <Select
                                        value={getValidPeriodTime(period.to, '10:00')}
                                        onValueChange={(nextValue) =>
                                            updatePeriod(index, (current) => ({
                                                ...current,
                                                to: nextValue,
                                            }))
                                        }
                                    >
                                        <SelectTrigger className="w-full">
                                            <SelectValue />
                                        </SelectTrigger>
                                        <SelectContent>
                                            {HOUR_TIME_OPTIONS.map((timeOption) => (
                                                <SelectItem
                                                    key={`to-${timeOption}`}
                                                    value={timeOption}
                                                >
                                                    {timeOption}
                                                </SelectItem>
                                            ))}
                                        </SelectContent>
                                    </Select>
                                </div>
                                <div className="grid gap-2">
                                    <Label>{t('slot_duration_minutes')}</Label>
                                    <Select
                                        value={String(
                                            getValidSlotDuration(
                                                period.slot_duration_minutes,
                                            ),
                                        )}
                                        onValueChange={(nextValue) =>
                                            updatePeriod(index, (current) => ({
                                                ...current,
                                                slot_duration_minutes:
                                                    getValidSlotDuration(
                                                        Number.parseInt(
                                                            nextValue,
                                                            10,
                                                        ),
                                                    ),
                                            }))
                                        }
                                    >
                                        <SelectTrigger className="w-full">
                                            <SelectValue />
                                        </SelectTrigger>
                                        <SelectContent>
                                            {SLOT_DURATION_OPTIONS.map(
                                                (minutes) => (
                                                    <SelectItem
                                                        key={minutes}
                                                        value={String(minutes)}
                                                    >
                                                        {minutes} min
                                                    </SelectItem>
                                                ),
                                            )}
                                        </SelectContent>
                                    </Select>
                                </div>
                            </div>

                            <div className="flex items-center justify-between">
                                <p className="text-sm text-muted-foreground">
                                    {index === 0
                                        ? t('base_period_cannot_remove')
                                        : t('additional_period')}
                                </p>
                                <Button
                                    type="button"
                                    variant="ghost"
                                    size="sm"
                                    disabled={index === 0}
                                    onClick={() => removePeriod(index)}
                                >
                                    <Trash2 className="size-4" />
                                    {t('remove')}
                                </Button>
                            </div>
                        </div>
                    ))}
                </div>
            </div>

            <div className="grid grid-cols-1 gap-3">
                <div className="grid gap-2">
                    <Label>{t('advance_days')}</Label>
                    <Input
                        min={1}
                        max={365}
                        type="number"
                        value={value.max_advance_days}
                        onChange={(event) =>
                            onChange({
                                ...value,
                                max_advance_days:
                                    Number.parseInt(event.target.value, 10) ||
                                    0,
                            })
                        }
                    />
                </div>
            </div>

            {firstError !== undefined && (
                <p className="text-sm text-red-500">{firstError}</p>
            )}

            <Button type="submit" disabled={isSaving} className="w-full">
                {isSaving ? (
                    <>
                        <RefreshCcw className="size-4 animate-spin" />
                        {t('saving')}
                    </>
                ) : (
                    t('save_global_settings')
                )}
            </Button>
        </form>
    );
}
