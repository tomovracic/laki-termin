import { RefreshCcw } from 'lucide-react';
import type { FormEvent } from 'react';
import type { InactivePeriodFormValue, ManagedTerrain } from '@/components/admin/types';
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
import { cn } from '@/lib/utils';

type InactivePeriodsFormProps = {
    value: InactivePeriodFormValue;
    terrains: ManagedTerrain[];
    isSaving: boolean;
    errors: Record<string, string[]>;
    onChange: (value: InactivePeriodFormValue) => void;
    onSubmit: (event: FormEvent<HTMLFormElement>) => void;
};

const REASON_OPTIONS = ['rain', 'maintenance', 'other'] as const;

const HOUR_TIME_OPTIONS = Array.from(
    { length: 24 },
    (_, hour) => `${String(hour).padStart(2, '0')}:00`,
);

function reasonLabel(
    reason: (typeof REASON_OPTIONS)[number],
    t: (key: string) => string,
): string {
    if (reason === 'rain') {
        return t('inactive_reason_rain');
    }

    if (reason === 'maintenance') {
        return t('inactive_reason_maintenance');
    }

    return t('inactive_reason_other');
}

function getValidPeriodTime(value: string, fallback: string): string {
    return HOUR_TIME_OPTIONS.includes(value) ? value : fallback;
}

export function InactivePeriodsForm({
    value,
    terrains,
    isSaving,
    errors,
    onChange,
    onSubmit,
}: InactivePeriodsFormProps) {
    const { t } = useI18n();
    const firstError = Object.values(errors)[0]?.[0];
    const isTimeRange = value.block_type === 'time_range';

    return (
        <form className="space-y-4 rounded-xl border p-4" onSubmit={onSubmit}>
            <div className="grid gap-2">
                <Label>{t('blocked_period_type')}</Label>
                <Select
                    value={value.block_type}
                    onValueChange={(selected) => {
                        const blockType = selected as InactivePeriodFormValue['block_type'];
                        onChange({
                            ...value,
                            block_type: blockType,
                            to_date:
                                blockType === 'time_range'
                                    ? value.from_date
                                    : value.to_date,
                        });
                    }}
                >
                    <SelectTrigger>
                        <SelectValue />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem value="full_day">{t('blocked_full_day')}</SelectItem>
                        <SelectItem value="time_range">{t('blocked_time_range')}</SelectItem>
                    </SelectContent>
                </Select>
            </div>

            <div className="grid gap-4 md:grid-cols-2">
                <div className="grid gap-2">
                    <Label htmlFor="blocked-from-date">
                        {isTimeRange ? t('blocked_date') : t('blocked_from_date')}
                    </Label>
                    <Input
                        id="blocked-from-date"
                        type="date"
                        required
                        value={value.from_date}
                        onChange={(event) => {
                            const fromDate = event.target.value;
                            onChange({
                                ...value,
                                from_date: fromDate,
                                to_date: isTimeRange
                                    ? fromDate
                                    : value.to_date === '' || value.to_date < fromDate
                                      ? fromDate
                                      : value.to_date,
                            });
                        }}
                    />
                    {errors.from_date?.[0] !== undefined && (
                        <p className="text-sm text-red-500">{errors.from_date[0]}</p>
                    )}
                </div>

                {!isTimeRange && (
                    <div className="grid gap-2">
                        <Label htmlFor="blocked-to-date">{t('blocked_to_date')}</Label>
                        <Input
                            id="blocked-to-date"
                            type="date"
                            required
                            min={value.from_date}
                            value={value.to_date}
                            onChange={(event) =>
                                onChange({
                                    ...value,
                                    to_date: event.target.value,
                                })
                            }
                        />
                        {errors.to_date?.[0] !== undefined && (
                            <p className="text-sm text-red-500">{errors.to_date[0]}</p>
                        )}
                    </div>
                )}
            </div>

            {isTimeRange && (
                <div className="grid gap-4 md:grid-cols-2">
                    <div className="grid gap-2">
                        <Label>{t('from')}</Label>
                        <Select
                            value={getValidPeriodTime(value.from_time, '20:00')}
                            onValueChange={(selected) =>
                                onChange({
                                    ...value,
                                    from_time: selected,
                                })
                            }
                        >
                            <SelectTrigger>
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                {HOUR_TIME_OPTIONS.map((time) => (
                                    <SelectItem key={time} value={time}>
                                        {time}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                        {errors.from_time?.[0] !== undefined && (
                            <p className="text-sm text-red-500">{errors.from_time[0]}</p>
                        )}
                    </div>

                    <div className="grid gap-2">
                        <Label>{t('to')}</Label>
                        <Select
                            value={getValidPeriodTime(value.to_time, '23:00')}
                            onValueChange={(selected) =>
                                onChange({
                                    ...value,
                                    to_time: selected,
                                })
                            }
                        >
                            <SelectTrigger>
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                {HOUR_TIME_OPTIONS.map((time) => (
                                    <SelectItem key={time} value={time}>
                                        {time}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                        {errors.to_time?.[0] !== undefined && (
                            <p className="text-sm text-red-500">{errors.to_time[0]}</p>
                        )}
                    </div>
                </div>
            )}

            <div className="grid gap-4 md:grid-cols-2">
                <div className="grid gap-2">
                    <Label>{t('terrain')}</Label>
                    <Select
                        value={
                            value.terrain_id === null
                                ? 'all'
                                : `${value.terrain_id}`
                        }
                        onValueChange={(selected) =>
                            onChange({
                                ...value,
                                terrain_id:
                                    selected === 'all'
                                        ? null
                                        : Number.parseInt(selected, 10),
                            })
                        }
                    >
                        <SelectTrigger>
                            <SelectValue placeholder={t('all_terrains')} />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="all">{t('all_terrains')}</SelectItem>
                            {terrains.map((terrain) => (
                                <SelectItem key={terrain.id} value={`${terrain.id}`}>
                                    {terrain.name}
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>
                    {errors.terrain_id?.[0] !== undefined && (
                        <p className="text-sm text-red-500">{errors.terrain_id[0]}</p>
                    )}
                </div>

                <div className="grid gap-2">
                    <Label>{t('reason')}</Label>
                    <Select
                        value={value.reason}
                        onValueChange={(selected) =>
                            onChange({
                                ...value,
                                reason: selected as InactivePeriodFormValue['reason'],
                            })
                        }
                    >
                        <SelectTrigger>
                            <SelectValue />
                        </SelectTrigger>
                        <SelectContent>
                            {REASON_OPTIONS.map((reason) => (
                                <SelectItem key={reason} value={reason}>
                                    {reasonLabel(reason, t)}
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>
                    {errors.reason?.[0] !== undefined && (
                        <p className="text-sm text-red-500">{errors.reason[0]}</p>
                    )}
                </div>
            </div>

            <div className="grid gap-2">
                <Label htmlFor="blocked-note">{t('note')}</Label>
                <textarea
                    id="blocked-note"
                    rows={3}
                    value={value.note}
                    onChange={(event) =>
                        onChange({
                            ...value,
                            note: event.target.value,
                        })
                    }
                    placeholder={t('optional_note')}
                    className={cn(
                        'border-input placeholder:text-muted-foreground flex min-h-20 w-full rounded-md border bg-transparent px-3 py-2 text-base shadow-xs transition-[color,box-shadow] outline-none md:text-sm',
                        'focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px]',
                    )}
                />
                {errors.note?.[0] !== undefined && (
                    <p className="text-sm text-red-500">{errors.note[0]}</p>
                )}
            </div>

            {firstError !== undefined && errors.from_date === undefined && (
                <p className="text-sm text-red-500">{firstError}</p>
            )}

            <Button type="submit" disabled={isSaving}>
                {isSaving ? (
                    <>
                        <RefreshCcw className="size-4 animate-spin" />
                        {t('adding')}
                    </>
                ) : (
                    t('add_blocked_day')
                )}
            </Button>
        </form>
    );
}
