import type { ReportReservation } from '@/components/admin/report-types';
import { Badge } from '@/components/ui/badge';
import { useI18n } from '@/lib/i18n';

type ReportReservationsTableProps = {
    reservations: ReportReservation[];
    showCancelledAt?: boolean;
};

function toTime(value?: string | null): string {
    if (value === undefined || value === null || value === '') {
        return '';
    }

    const timeMatch = value.match(/(?:T|\s)(\d{2}:\d{2})/);

    if (timeMatch?.[1] !== undefined) {
        return timeMatch[1];
    }

    return value.slice(0, 5);
}

function formatDate(value: string, locale: string): string {
    const date = new Date(value.includes('T') ? value : `${value}T00:00:00`);

    return date.toLocaleDateString(locale === 'hr' ? 'hr-HR' : 'en-GB', {
        day: '2-digit',
        month: '2-digit',
        year: 'numeric',
    });
}

function formatDateTime(value: string, locale: string): string {
    const date = new Date(value);

    return date.toLocaleString(locale === 'hr' ? 'hr-HR' : 'en-GB', {
        day: '2-digit',
        month: '2-digit',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    });
}

export function ReportReservationsTable({
    reservations,
    showCancelledAt = false,
}: ReportReservationsTableProps) {
    const { locale, t } = useI18n();

    if (reservations.length === 0) {
        return <p className="text-sm text-muted-foreground">{t('report_no_results')}</p>;
    }

    return (
        <div className="overflow-x-auto rounded-lg border">
            <table className="w-full min-w-[900px] text-sm">
                <thead className="bg-muted/50">
                    <tr>
                        <th className="px-4 py-3 text-left font-medium">{t('report_user')}</th>
                        <th className="px-4 py-3 text-left font-medium">{t('terrain')}</th>
                        <th className="px-4 py-3 text-left font-medium">{t('report_date')}</th>
                        <th className="px-4 py-3 text-left font-medium">{t('report_time')}</th>
                        <th className="px-4 py-3 text-left font-medium">{t('report_status')}</th>
                        {showCancelledAt && (
                            <th className="px-4 py-3 text-left font-medium">{t('report_cancelled_at')}</th>
                        )}
                        {showCancelledAt && (
                            <th className="px-4 py-3 text-left font-medium">{t('reason')}</th>
                        )}
                    </tr>
                </thead>
                <tbody>
                    {reservations.map((reservation) => {
                        const date = reservation.reserved_for_date
                            ? formatDate(reservation.reserved_for_date, locale)
                            : reservation.slot?.starts_at
                              ? formatDate(reservation.slot.starts_at, locale)
                              : '';
                        const fromTime = reservation.reserved_from_time
                            ? toTime(reservation.reserved_from_time)
                            : toTime(reservation.slot?.starts_at);
                        const toTimeValue = reservation.reserved_to_time
                            ? toTime(reservation.reserved_to_time)
                            : toTime(reservation.slot?.ends_at);

                        return (
                            <tr key={reservation.id} className="border-t">
                                <td className="px-4 py-3">
                                    <div className="font-medium">{reservation.user?.name ?? '—'}</div>
                                    <div className="text-xs text-muted-foreground">{reservation.user?.email}</div>
                                </td>
                                <td className="px-4 py-3">{reservation.slot?.terrain?.name ?? '—'}</td>
                                <td className="px-4 py-3">{date}</td>
                                <td className="px-4 py-3">
                                    {fromTime && toTimeValue ? `${fromTime} – ${toTimeValue}` : '—'}
                                </td>
                                <td className="px-4 py-3">
                                    <Badge variant="outline">{reservation.display_status}</Badge>
                                </td>
                                {showCancelledAt && (
                                    <td className="px-4 py-3">
                                        {reservation.cancelled_at
                                            ? formatDateTime(reservation.cancelled_at, locale)
                                            : '—'}
                                    </td>
                                )}
                                {showCancelledAt && (
                                    <td className="px-4 py-3 text-muted-foreground">
                                        {reservation.cancel_reason ?? '—'}
                                    </td>
                                )}
                            </tr>
                        );
                    })}
                </tbody>
            </table>
        </div>
    );
}
