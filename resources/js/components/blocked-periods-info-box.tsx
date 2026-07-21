import { BanIcon } from 'lucide-react';
import type { InactivePeriod } from '@/components/admin/types';
import { useI18n } from '@/lib/i18n';

type BlockedPeriodsInfoBoxProps = {
    periods: InactivePeriod[];
    selectedDate: string;
};

function parseIsoDate(value: string): Date {
    const [year, month, day] = value.split('-').map((part) => Number.parseInt(part, 10));
    return new Date(year, month - 1, day);
}

function formatDate(value: string, locale: string): string {
    return parseIsoDate(value).toLocaleDateString(locale, {
        weekday: 'short',
        year: 'numeric',
        month: 'long',
        day: 'numeric',
    });
}

function formatDateRange(fromDate: string, toDate: string, locale: string): string {
    if (fromDate === toDate) {
        return formatDate(fromDate, locale);
    }

    return `${formatDate(fromDate, locale)} – ${formatDate(toDate, locale)}`;
}

function inactiveReasonLabel(
    reason: InactivePeriod['reason'],
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

function formatPeriodDateLabel(
    period: InactivePeriod,
    selectedDate: string,
    locale: string,
    t: (key: string) => string,
): string {
    if (period.from_date !== period.to_date) {
        return formatDateRange(period.from_date, period.to_date, locale);
    }

    if (period.from_date === selectedDate) {
        return formatDate(selectedDate, locale);
    }

    return formatDate(period.from_date, locale);
}

function formatPeriodTimeLabel(
    period: InactivePeriod,
    t: (key: string) => string,
): string | null {
    if (period.block_type === 'time_range' && period.from_time && period.to_time) {
        return `${period.from_time} – ${period.to_time}`;
    }

    if (period.from_date === period.to_date) {
        return t('blocked_full_day');
    }

    return null;
}

export function BlockedPeriodsInfoBox({ periods, selectedDate }: BlockedPeriodsInfoBoxProps) {
    const { locale, t } = useI18n();

    if (periods.length === 0) {
        return null;
    }

    return (
        <div className="space-y-2">
            {periods.map((period) => {
                const dateLabel = formatPeriodDateLabel(period, selectedDate, locale, t);
                const timeLabel = formatPeriodTimeLabel(period, t);

                return (
                    <div
                        key={period.id}
                        className="flex gap-3 rounded-xl border border-red-200/80 bg-red-50/70 px-4 py-3 dark:border-red-900/60 dark:bg-red-950/25"
                        role="alert"
                    >
                        <BanIcon className="mt-0.5 size-5 shrink-0 text-red-600 dark:text-red-400" />
                        <div className="space-y-1 text-sm">
                            <p className="font-medium text-foreground">{t('day_blocked_description')}</p>
                            <p className="text-muted-foreground">
                                <span className="font-medium text-foreground">{t('blocked_date')}:</span>{' '}
                                {dateLabel}
                            </p>
                            {timeLabel !== null && (
                                <p className="text-muted-foreground">
                                    <span className="font-medium text-foreground">
                                        {t('blocked_time_range')}:
                                    </span>{' '}
                                    {timeLabel}
                                </p>
                            )}
                            <p className="text-muted-foreground">
                                <span className="font-medium text-foreground">{t('reason')}:</span>{' '}
                                {inactiveReasonLabel(period.reason, t)}
                            </p>
                            {period.note !== null && period.note.trim() !== '' && (
                                <p className="text-muted-foreground">
                                    <span className="font-medium text-foreground">{t('note')}:</span>{' '}
                                    {period.note}
                                </p>
                            )}
                        </div>
                    </div>
                );
            })}
        </div>
    );
}
