import { Head } from '@inertiajs/react';
import { useMemo, useState } from 'react';
import { ChevronLeft, ChevronRight } from 'lucide-react';
import { Avatar, AvatarFallback } from '@/components/ui/avatar';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import AppLayout from '@/layouts/app-layout';
import { useI18n } from '@/lib/i18n';
import { dashboard } from '@/routes';
import dashboardRoutes from '@/routes/dashboard';
import reservationsRoutes from '@/routes/reservations';
import type { BreadcrumbItem } from '@/types';

type SlotStatus = 'available' | 'reserved' | 'blocked' | 'maintenance' | 'past';

type ReservationSlot = {
    id: number;
    starts_at: string;
    ends_at: string;
    status: SlotStatus;
    reservation_id_for_current_user?: number | null;
    can_cancel?: boolean;
    reserved_by?: {
        first_name: string;
        last_name: string;
    } | null;
};

type TerrainDetails = {
    id: number;
    name: string;
    code: string;
    description: string | null;
};

type TerrainSlotsPageProps = {
    terrain: TerrainDetails;
    selected_date: string;
    max_advance_days: number;
    slots: ReservationSlot[];
    token_count: number;
};

type SlotsPayload = {
    selected_date: string;
    max_advance_days: number;
    slots: ReservationSlot[];
    token_count: number;
};

type BulkReservationSuccessResponse = {
    meta?: {
        tokens_remaining?: number;
    };
};

type ReservationErrorResponse = {
    errors?: {
        reservation?: string[];
    };
};

type FetchSlotsOptions = {
    resetFeedback?: boolean;
};

function csrfToken(): string {
    return (
        document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? ''
    );
}

function toTime(value: string): string {
    return new Date(value).toLocaleTimeString([], {
        hour: '2-digit',
        minute: '2-digit',
        hour12: false,
        hourCycle: 'h23',
    });
}

function parseIsoDate(value: string): Date {
    const [year, month, day] = value.split('-').map((part) => Number.parseInt(part, 10));
    return new Date(year, month - 1, day);
}

function toIsoDate(date: Date): string {
    const year = date.getFullYear();
    const month = `${date.getMonth() + 1}`.padStart(2, '0');
    const day = `${date.getDate()}`.padStart(2, '0');
    return `${year}-${month}-${day}`;
}

function addDays(value: string, days: number): string {
    const date = parseIsoDate(value);
    date.setDate(date.getDate() + days);
    return toIsoDate(date);
}

function displayDate(value: string, locale: string): string {
    return parseIsoDate(value).toLocaleDateString(locale, {
        weekday: 'short',
        year: 'numeric',
        month: 'long',
        day: 'numeric',
    });
}

function tokenUnitLabel(count: number, t: (key: string) => string): string {
    return count === 1 ? t('token_unit_singular') : t('token_unit_plural');
}

function isSlotInFuture(slot: ReservationSlot): boolean {
    return new Date(slot.starts_at).getTime() > Date.now();
}

function statusLabel(status: SlotStatus): 'free' | 'reserved' | 'disabled' | 'past' {
    if (status === 'available') {
        return 'free';
    }

    if (status === 'reserved') {
        return 'reserved';
    }

    if (status === 'past') {
        return 'past';
    }

    return 'disabled';
}

function initialsFromName(name: string): string {
    const parts = name
        .trim()
        .split(/\s+/)
        .filter((part) => part.length > 0);

    if (parts.length === 0) {
        return '?';
    }

    if (parts.length === 1) {
        return parts[0].slice(0, 2).toUpperCase();
    }

    return `${parts[0][0] ?? ''}${parts[parts.length - 1][0] ?? ''}`.toUpperCase();
}

export default function TerrainReservationPage({
    terrain,
    selected_date: initialDate,
    max_advance_days: initialMaxAdvanceDays,
    slots: initialSlots,
    token_count: initialTokenCount,
}: TerrainSlotsPageProps) {
    const { locale, t } = useI18n();
    const [selectedDate, setSelectedDate] = useState<string>(initialDate);
    const [maxAdvanceDays, setMaxAdvanceDays] = useState<number>(initialMaxAdvanceDays);
    const [slots, setSlots] = useState<ReservationSlot[]>(initialSlots);
    const [tokenCount, setTokenCount] = useState<number>(initialTokenCount);
    const [selectedSlotIds, setSelectedSlotIds] = useState<number[]>([]);
    const [isLoading, setIsLoading] = useState<boolean>(false);
    const [isSubmitting, setIsSubmitting] = useState<boolean>(false);
    const [isCancellingSlotId, setIsCancellingSlotId] = useState<number | null>(null);
    const [isConfirmOpen, setIsConfirmOpen] = useState<boolean>(false);
    const [errorMessage, setErrorMessage] = useState<string | null>(null);
    const [message, setMessage] = useState<string | null>(null);

    const selectedCount = selectedSlotIds.length;
    const selectedTimeRanges = useMemo(
        () =>
            slots
                .filter((slot) => selectedSlotIds.includes(slot.id))
                .map((slot) => `${toTime(slot.starts_at)} - ${toTime(slot.ends_at)}`),
        [selectedSlotIds, slots],
    );

    const breadcrumbs: BreadcrumbItem[] = [
        {
            title: t('dashboard'),
            href: dashboard(),
        },
        {
            title: terrain.name,
            href: dashboardRoutes.terrains.show({ terrain: terrain.id }),
        },
    ];
    const maxSelectableDate = addDays(toIsoDate(new Date()), maxAdvanceDays);

    async function fetchSlots(
        date: string,
        options: FetchSlotsOptions = {},
    ): Promise<boolean> {
        const resetFeedback = options.resetFeedback ?? true;
        setIsLoading(true);
        if (resetFeedback) {
            setErrorMessage(null);
            setMessage(null);
        }

        try {
            const response = await fetch(
                dashboardRoutes.terrains.slots.url(
                    { terrain: terrain.id },
                    { query: { date } },
                ),
                {
                    headers: {
                        Accept: 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                },
            );

            if (!response.ok) {
                setErrorMessage(t('unable_load_slots_selected_date'));
                return false;
            }

            const payload = (await response.json()) as { data: SlotsPayload };
            setSelectedDate(payload.data.selected_date);
            setMaxAdvanceDays(payload.data.max_advance_days);
            setSlots(payload.data.slots);
            setTokenCount(payload.data.token_count);
            setSelectedSlotIds([]);
            return true;
        } catch {
            setErrorMessage(t('unable_load_slots_selected_date'));
            return false;
        } finally {
            setIsLoading(false);
        }
    }

    async function handleDateStep(days: number): Promise<void> {
        if (days > 0 && selectedDate >= maxSelectableDate) {
            return;
        }

        const nextDate = addDays(selectedDate, days);
        await fetchSlots(nextDate);
    }

    function toggleSlot(slot: ReservationSlot): void {
        if (slot.status !== 'available') {
            return;
        }

        setErrorMessage(null);
        setMessage(null);

        setSelectedSlotIds((current) => {
            if (current.includes(slot.id)) {
                return current.filter((slotId) => slotId !== slot.id);
            }

            if (current.length >= tokenCount) {
                setErrorMessage(t('not_enough_tokens_more_slots'));
                return current;
            }

            return [...current, slot.id];
        });
    }

    async function confirmReservation(): Promise<void> {
        if (selectedSlotIds.length === 0) {
            return;
        }

        setIsSubmitting(true);
        setErrorMessage(null);
        setMessage(null);

        const response = await fetch(reservationsRoutes.bulkStore.url(), {
            method: 'POST',
            headers: {
                Accept: 'application/json',
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrfToken(),
                'X-Requested-With': 'XMLHttpRequest',
            },
            body: JSON.stringify({
                reservation_slot_ids: selectedSlotIds,
            }),
        });

        if (!response.ok) {
            const errorPayload =
                (await response.json().catch(() => ({}))) as ReservationErrorResponse;
            await fetchSlots(selectedDate, { resetFeedback: false });
            setErrorMessage(
                errorPayload.errors?.reservation?.[0] ??
                    t('reservation_failed'),
            );
            setIsSubmitting(false);
            setIsConfirmOpen(false);
            return;
        }

        const payload = (await response.json()) as BulkReservationSuccessResponse;
        setTokenCount(payload.meta?.tokens_remaining ?? Math.max(0, tokenCount - selectedCount));
        setIsSubmitting(false);
        setIsConfirmOpen(false);
        await fetchSlots(selectedDate, { resetFeedback: false });
        setMessage(t('reservation_created'));
    }

    async function cancelReservation(slot: ReservationSlot): Promise<void> {
        const reservationId = slot.reservation_id_for_current_user;
        const canCancelSlot =
            slot.can_cancel === true &&
            reservationId !== undefined &&
            reservationId !== null &&
            isSlotInFuture(slot);

        if (!canCancelSlot) {
            setErrorMessage(t('cannot_cancel_past_slot'));
            return;
        }

        setIsCancellingSlotId(slot.id);
        setErrorMessage(null);
        setMessage(null);

        const response = await fetch(
            reservationsRoutes.cancel.url({ reservation: reservationId }),
            {
                method: 'POST',
                headers: {
                    Accept: 'application/json',
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrfToken(),
                    'X-Requested-With': 'XMLHttpRequest',
                },
                body: JSON.stringify({}),
            },
        );

        if (!response.ok) {
            const errorPayload =
                (await response.json().catch(() => ({}))) as ReservationErrorResponse;
            setErrorMessage(
                errorPayload.errors?.reservation?.[0] ?? t('reservation_cancel_failed'),
            );
            setIsCancellingSlotId(null);
            return;
        }

        setIsCancellingSlotId(null);
        await fetchSlots(selectedDate, { resetFeedback: false });
        setMessage(t('reservation_cancelled'));
    }

    const isNextDisabled = isLoading || selectedDate >= maxSelectableDate;
    const isPreviousDisabled = isLoading;

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={`${t('terrain')} ${terrain.name}`} />

            <div className="flex h-full flex-1 flex-col gap-4 overflow-x-auto p-4 pb-28">
                <div className="space-y-3">
                    <div className="space-y-1">
                        <h1 className="text-xl font-semibold">{terrain.name}</h1>
                        <p className="text-sm text-muted-foreground">
                            {terrain.description ?? `${t('code')}: ${terrain.code}`}
                        </p>
                    </div>
                    <div className="flex flex-wrap items-center gap-3">
                        <div className="inline-flex items-center gap-2 rounded-2xl border border-primary/40 bg-primary/10 p-2 shadow-md shadow-primary/15 ring-1 ring-primary/20 backdrop-blur-sm">
                            <Button
                                type="button"
                                variant="ghost"
                                size="icon"
                                disabled={isPreviousDisabled}
                                onClick={() => void handleDateStep(-1)}
                                className="rounded-xl border border-primary/40 bg-white text-zinc-800 hover:bg-primary/10"
                            >
                                <ChevronLeft className="size-4" />
                            </Button>
                            <span className="min-w-52 rounded-xl border border-primary/45 bg-white px-4 py-2 text-center text-sm font-bold text-zinc-900 shadow-sm">
                                {isLoading
                                    ? t('loading')
                                    : displayDate(selectedDate, locale)}
                            </span>
                            <Button
                                type="button"
                                variant="ghost"
                                size="icon"
                                disabled={isNextDisabled}
                                onClick={() => void handleDateStep(1)}
                                className="rounded-xl border border-primary/40 bg-white text-zinc-800 hover:bg-primary/10"
                            >
                                <ChevronRight className="size-4" />
                            </Button>
                        </div>
                        <div className="inline-flex h-14 items-center rounded-2xl border border-primary/40 bg-primary/10 px-4 shadow-md shadow-primary/15 ring-1 ring-primary/20 backdrop-blur-sm">
                            <div className="flex items-baseline gap-1.5">
                                <span className="text-2xl font-black leading-none text-foreground">{tokenCount}</span>
                                <span className="text-sm font-semibold text-zinc-700">
                                    {tokenUnitLabel(tokenCount, t)}
                                </span>
                            </div>
                        </div>
                    </div>
                    <div className="flex flex-wrap items-center gap-2 text-xs text-muted-foreground">
                        <span className="inline-flex items-center gap-1.5 rounded-full border border-emerald-500 bg-emerald-50 px-2 py-1 text-emerald-700 dark:border-emerald-700 dark:bg-emerald-950/30 dark:text-emerald-300">
                            <span className="size-2 rounded-full bg-emerald-500 dark:bg-emerald-400" />
                            {t('free')}
                        </span>
                        <span className="inline-flex items-center gap-1.5 rounded-full border border-black bg-black px-2 py-1 text-white">
                            <span className="size-2 rounded-full bg-white/90" />
                            {t('reserved')}
                        </span>
                        <span className="inline-flex items-center gap-1.5 rounded-full border border-emerald-600 bg-emerald-600 px-2 py-1 text-white dark:border-emerald-500 dark:bg-emerald-500 dark:text-black">
                            <span className="size-2 rounded-full bg-white/90 dark:bg-black/80" />
                            {t('selected_slots')}
                        </span>
                        <span className="inline-flex items-center gap-1.5 rounded-full border border-border bg-muted/50 px-2 py-1">
                            <span className="size-2 rounded-full bg-muted-foreground/50" />
                            {t('past')}
                        </span>
                    </div>
                    {errorMessage !== null && (
                        <p className="text-sm text-red-500">{errorMessage}</p>
                    )}
                    {message !== null && (
                        <p className="text-sm text-emerald-600">{message}</p>
                    )}
                </div>
 
                <div className="grid gap-3 md:grid-cols-2 xl:grid-cols-3">
                    {slots.map((slot) => {
                        const isSelected = selectedSlotIds.includes(slot.id);
                        const isAvailable = slot.status === 'available';
                        const isReserved = slot.status === 'reserved';
                        const isPast = slot.status === 'past';
                        const canCancelSlot =
                            slot.can_cancel === true &&
                            slot.reservation_id_for_current_user !== undefined &&
                            slot.reservation_id_for_current_user !== null &&
                            isSlotInFuture(slot);
                        const isDisabled = !isAvailable && !isReserved;
                        const cardClassName = isSelected
                            ? 'border-emerald-500 bg-emerald-100 ring-2 ring-emerald-300/70 shadow-sm dark:border-emerald-500 dark:bg-emerald-950/40 dark:ring-emerald-700/60'
                            : isAvailable
                              ? 'border-emerald-400/80 bg-card hover:border-emerald-500 hover:bg-emerald-50/40 dark:border-emerald-700/80 dark:hover:bg-emerald-950/20'
                              : isReserved
                                ? 'border-black bg-muted/55 dark:border-black'
                                : isPast
                                  ? 'border-border bg-muted/70 opacity-90'
                                : 'border-border bg-muted/60';
                        const statusBadgeClassName = isSelected
                            ? 'border-emerald-600 bg-emerald-600 text-white dark:border-emerald-500 dark:bg-emerald-500 dark:text-black'
                            : isAvailable
                              ? 'border-emerald-500 text-emerald-700 dark:text-emerald-300'
                              : isReserved
                                ? 'border-black bg-black text-white dark:border-black dark:bg-black dark:text-white'
                                : isPast
                                  ? 'border-zinc-500 bg-zinc-500/20 text-zinc-700 dark:border-zinc-400 dark:bg-zinc-400/25 dark:text-zinc-200'
                                : 'border-muted-foreground/40 text-muted-foreground';

                        return (
                            <button
                                key={slot.id}
                                type="button"
                                onClick={() => toggleSlot(slot)}
                                aria-pressed={isSelected}
                                className={`rounded-md border px-3 py-2 text-left transition ${
                                    isAvailable || canCancelSlot
                                        ? 'cursor-pointer'
                                        : 'cursor-not-allowed'
                                } ${cardClassName}`}
                            >
                                <div className="flex items-center justify-between gap-2">
                                    <p className={`font-medium ${isSelected ? 'text-primary' : 'text-foreground'}`}>
                                        {toTime(slot.starts_at)} - {toTime(slot.ends_at)}
                                    </p>
                                    <Badge variant="outline" className={statusBadgeClassName}>
                                        {t(statusLabel(slot.status))}
                                    </Badge>
                                </div>

                                {isAvailable && (
                                    <p className="mt-1 text-xs text-muted-foreground">
                                        {isSelected
                                            ? t('selected_for_reservation')
                                            : t('click_select_free_slot')}
                                    </p>
                                )}

                                {isDisabled && (
                                    <p className="mt-1 text-xs text-muted-foreground">
                                        {t('slot_unavailable')}
                                    </p>
                                )}

                                {slot.status === 'reserved' && slot.reserved_by !== null && slot.reserved_by !== undefined && (
                                    <div className="mt-2 flex items-center gap-2">
                                        <Avatar className="size-6">
                                            <AvatarFallback className="bg-white text-[10px] font-semibold text-slate-900">
                                                {initialsFromName(`${slot.reserved_by.first_name} ${slot.reserved_by.last_name}`)}
                                            </AvatarFallback>
                                        </Avatar>
                                        <span className="text-xs text-muted-foreground">
                                            {slot.reserved_by.first_name} {slot.reserved_by.last_name}
                                        </span>
                                        {canCancelSlot && (
                                            <Button
                                                type="button"
                                                variant="outline"
                                                size="sm"
                                                className="ml-auto h-7 border-red-300 px-2 text-[11px] text-red-700 hover:border-red-400 hover:bg-red-50"
                                                disabled={isCancellingSlotId === slot.id}
                                                onClick={(event) => {
                                                    event.stopPropagation();
                                                    void cancelReservation(slot);
                                                }}
                                            >
                                                {isCancellingSlotId === slot.id ? t('cancelling') : t('cancel_reservation')}
                                            </Button>
                                        )}
                                    </div>
                                )}
                            </button>
                        );
                    })}

                    {slots.length === 0 && (
                        <Card className="md:col-span-2 xl:col-span-3">
                            <CardContent>
                                <p className="text-sm text-muted-foreground">
                                    {t('no_slots_this_day')}
                                </p>
                            </CardContent>
                        </Card>
                    )}
                </div>
            </div>

            <div className="fixed right-0 bottom-0 left-0 z-40 border-t bg-background/95 px-4 py-3 backdrop-blur-sm md:left-(--sidebar-width)">
                <div className="mx-auto flex w-full max-w-5xl items-center justify-between gap-3">
                    <div className="text-sm">
                        <p>
                            {t('tokens_available')}: <span className="font-semibold">{tokenCount}</span>
                        </p>
                        <p>
                            {t('selected_slots')}:{' '}
                            <span className="font-semibold">{selectedCount}</span>
                        </p>
                    </div>
                    <Button
                        disabled={selectedCount === 0 || isSubmitting}
                        onClick={() => setIsConfirmOpen(true)}
                    >
                        {t('confirm_selected_slots')}
                    </Button>
                </div>
            </div>

            <Dialog open={isConfirmOpen} onOpenChange={setIsConfirmOpen}>
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>{t('confirm_reservation')}</DialogTitle>
                        <DialogDescription>
                            {t('confirm_reservation_for_count_on_date')
                                .replace('{count}', `${selectedCount}`)
                                .replace('{date}', selectedDate)}
                        </DialogDescription>
                    </DialogHeader>

                    <div className="max-h-52 space-y-2 overflow-y-auto rounded-md border p-3">
                        {selectedTimeRanges.map((range) => (
                            <p key={range} className="text-sm">
                                {range}
                            </p>
                        ))}
                    </div>

                    <DialogFooter>
                        <Button
                            variant="outline"
                            onClick={() => setIsConfirmOpen(false)}
                            disabled={isSubmitting}
                        >
                            {t('cancel')}
                        </Button>
                        <Button onClick={() => void confirmReservation()} disabled={isSubmitting}>
                            {isSubmitting ? t('confirming') : t('confirm_reservation_action')}
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </AppLayout>
    );
}
