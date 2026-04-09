import { Head } from '@inertiajs/react';
import { Badge } from '@/components/ui/badge';
import { Card, CardContent } from '@/components/ui/card';
import AppLayout from '@/layouts/app-layout';
import { useI18n } from '@/lib/i18n';
import { dashboard } from '@/routes';
import type { BreadcrumbItem } from '@/types';

type ReservationSlot = {
    id: number;
    starts_at?: string;
    ends_at?: string;
    status: string;
    terrain: {
        id: number;
        name: string;
        code: string;
    } | null;
};

type UserReservation = {
    id: number;
    status: string | null;
    display_status: 'pending' | 'cancelled' | 'played';
    reserved_for_date?: string | null;
    reserved_from_time?: string | null;
    reserved_to_time?: string | null;
    slot: ReservationSlot | null;
};

type UserReservationsPageProps = {
    reservations: UserReservation[];
};

export default function UserReservationsPage({ reservations }: UserReservationsPageProps) {
    const { locale, t } = useI18n();

    const breadcrumbs: BreadcrumbItem[] = [
        {
            title: t('dashboard'),
            href: dashboard(),
        },
        {
            title: t('my_reservations'),
            href: '/dashboard/reservations',
        },
    ];

    function toTime(value?: string): string {
        if (value === undefined || value === null || value === '') {
            return '';
        }

        const timeMatch = value.match(/(?:T|\s)(\d{2}:\d{2})/);

        if (timeMatch?.[1] !== undefined) {
            return timeMatch[1];
        }

        return value;
    }

    function displayDate(reservation: UserReservation): string {
        if (reservation.reserved_for_date !== undefined && reservation.reserved_for_date !== null && reservation.reserved_for_date !== '') {
            return formatDate(reservation.reserved_for_date);
        }

        if (reservation.slot?.starts_at !== undefined) {
            return formatDate(reservation.slot.starts_at);
        }

        return '';
    }

    function displayFromTime(reservation: UserReservation): string {
        if (reservation.reserved_from_time !== undefined && reservation.reserved_from_time !== null && reservation.reserved_from_time !== '') {
            return toTime(reservation.reserved_from_time);
        }

        if (reservation.slot?.starts_at !== undefined) {
            return toTime(reservation.slot.starts_at);
        }

        return '';
    }

    function displayToTime(reservation: UserReservation): string {
        if (reservation.reserved_to_time !== undefined && reservation.reserved_to_time !== null && reservation.reserved_to_time !== '') {
            return toTime(reservation.reserved_to_time);
        }

        if (reservation.slot?.ends_at !== undefined) {
            return toTime(reservation.slot.ends_at);
        }

        return '';
    }

    function reservationSlotLabel(reservation: UserReservation): string {
        const date = displayDate(reservation);
        const fromTime = displayFromTime(reservation);
        const toTimeValue = displayToTime(reservation);

        if (date !== '' && fromTime !== '' && toTimeValue !== '') {
            return `${date} • ${fromTime} - ${toTimeValue}`;
        }

        return t('slot_unavailable');
    }

    function formatDate(value?: string): string {
        if (value === undefined || value === null || value === '') {
            return '';
        }

        const date = new Date(value);

        if (Number.isNaN(date.getTime())) {
            return value;
        }

        return date.toLocaleDateString(locale, {
            year: 'numeric',
            month: '2-digit',
            day: 'numeric',
        });
    }

    function reservationStatusLabel(status: UserReservation['display_status']): string {
        if (status === 'cancelled') {
            return t('cancelled');
        }

        if (status === 'played') {
            return t('played');
        }

        return t('pending');
    }

    function reservationStatusBadgeClassName(status: UserReservation['display_status']): string {
        if (status === 'cancelled') {
            return 'border-red-200 bg-red-50 text-red-700 dark:border-red-900/60 dark:bg-red-950/30 dark:text-red-300';
        }

        if (status === 'played') {
            return 'border-emerald-200 bg-emerald-50 text-emerald-700 dark:border-emerald-900/60 dark:bg-emerald-950/30 dark:text-emerald-300';
        }

        return 'border-amber-200 bg-amber-50 text-amber-700 dark:border-amber-900/60 dark:bg-amber-950/30 dark:text-amber-300';
    }

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={t('my_reservations')} />
            <div className="flex h-full flex-1 flex-col gap-4 overflow-x-auto p-4">
                <Card className="rounded-2xl border-border/60 bg-card/95">
                    <CardContent className="space-y-3 pt-6">
                        <div className="flex items-center justify-between">
                            <h2 className="text-lg font-semibold">{t('my_reservations')}</h2>
                            <Badge variant="secondary">{reservations.length}</Badge>
                        </div>

                        {reservations.length === 0 ? (
                            <p className="text-sm text-muted-foreground">{t('no_reservations_yet')}</p>
                        ) : (
                            <div className="space-y-2">
                                {reservations.map((reservation) => (
                                    <div
                                        key={reservation.id}
                                        className="flex flex-wrap items-center justify-between gap-3 rounded-lg border p-3"
                                    >
                                        <div className="space-y-1">
                                            <p className="text-sm font-medium">
                                                {reservation.slot?.terrain?.name ?? t('terrain')}
                                            </p>
                                            <p className="text-xs text-muted-foreground">
                                                {reservationSlotLabel(reservation)}
                                            </p>
                                        </div>
                                        <Badge
                                            variant="outline"
                                            className={reservationStatusBadgeClassName(reservation.display_status)}
                                        >
                                            {reservationStatusLabel(reservation.display_status)}
                                        </Badge>
                                    </div>
                                ))}
                            </div>
                        )}
                    </CardContent>
                </Card>
            </div>
        </AppLayout>
    );
}
