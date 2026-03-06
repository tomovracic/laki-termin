import { Head, Link } from '@inertiajs/react';
import { ChevronLeft, ChevronRight } from 'lucide-react';
import { useState } from 'react';
import { StatusBanner } from '@/components/admin/status-banner';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardFooter } from '@/components/ui/card';
import AppLayout from '@/layouts/app-layout';
import { useI18n } from '@/lib/i18n';
import { dashboard } from '@/routes';
import dashboardRoutes from '@/routes/dashboard';
import type { BreadcrumbItem } from '@/types';

type ReservationSlot = {
    id: number;
    starts_at: string;
    ends_at: string;
    status: string;
};

type DashboardTerrain = {
    id: number;
    name: string;
    code: string;
    description: string | null;
    is_active: boolean;
    available_slots_count: number;
    slots: ReservationSlot[];
};

type DashboardAvailabilityPayload = {
    selected_date: string;
    max_advance_days: number;
    terrains: DashboardTerrain[];
    token_count: number;
};

type DashboardPageProps = {
    selected_date: string;
    max_advance_days: number;
    terrains: DashboardTerrain[];
    token_count: number;
};

export default function Dashboard({
    selected_date: initialDate,
    max_advance_days: initialMaxAdvanceDays,
    terrains: initialTerrains,
    token_count: initialTokenCount,
}: DashboardPageProps) {
    const { locale, t } = useI18n();
    const [selectedDate, setSelectedDate] = useState<string>(initialDate);
    const [maxAdvanceDays, setMaxAdvanceDays] = useState<number>(initialMaxAdvanceDays);
    const [terrains, setTerrains] = useState<DashboardTerrain[]>(initialTerrains);
    const [tokenCount, setTokenCount] = useState<number>(initialTokenCount);
    const [isLoading, setIsLoading] = useState<boolean>(false);
    const [errorMessage, setErrorMessage] = useState<string | null>(null);

    const breadcrumbs: BreadcrumbItem[] = [
        {
            title: t('dashboard'),
            href: dashboard(),
        },
    ];

    function toTime(value: string): string {
        return new Date(value).toLocaleTimeString([], {
            hour: '2-digit',
            minute: '2-digit',
        hour12: false,
        hourCycle: 'h23',
        });
    }

    function imageStyleForTerrain(terrainId: number): string {
        const toneShift = terrainId % 7;
        const hue = 18 + toneShift * 3;
        const warmLight = 58 + toneShift;
        const warmDark = 42 + toneShift;

        return `
            radial-gradient(circle at 12% 20%, hsl(${hue + 8} 92% ${warmLight + 12}% / 0.38), transparent 45%),
            radial-gradient(circle at 86% 24%, hsl(${hue - 2} 88% ${warmLight + 6}% / 0.26), transparent 42%),
            linear-gradient(110deg, rgb(255 255 255 / 0.17) 0 7%, transparent 7% 14%, rgb(255 255 255 / 0.10) 14% 17%, transparent 17% 100%),
            linear-gradient(180deg, transparent 0 62%, rgb(255 255 255 / 0.22) 62% 64%, transparent 64% 100%),
            linear-gradient(152deg, hsl(${hue} 68% ${warmLight}%) 0%, hsl(${hue - 5} 61% ${warmDark}%) 100%)
        `;
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

    function displayDate(value: string): string {
        return parseIsoDate(value).toLocaleDateString(locale, {
            weekday: 'short',
            year: 'numeric',
            month: 'long',
            day: 'numeric',
        });
    }

    function tokenUnitLabel(count: number): string {
        return count === 1 ? t('token_unit_singular') : t('token_unit_plural');
    }

    async function refreshDashboardData(date: string): Promise<void> {
        setIsLoading(true);
        setErrorMessage(null);

        const response = await fetch(
            dashboardRoutes.availability.url({
                query: { date },
            }),
            {
                headers: {
                    Accept: 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
            },
        );

        if (!response.ok) {
            setErrorMessage(t('unable_load_terrains_selected_date'));
            setIsLoading(false);
            return;
        }

        const payload = (await response.json()) as { data: DashboardAvailabilityPayload };
        setSelectedDate(payload.data.selected_date);
        setMaxAdvanceDays(payload.data.max_advance_days);
        setTerrains(payload.data.terrains);
        setTokenCount(payload.data.token_count);
        setIsLoading(false);
    }

    async function handleDateStep(days: number): Promise<void> {
        const nextDate = addDays(selectedDate, days);
        await refreshDashboardData(nextDate);
    }

    const maxSelectableDate = addDays(toIsoDate(new Date()), maxAdvanceDays);
    const isNextDisabled = isLoading || selectedDate >= maxSelectableDate;
    const isPreviousDisabled = isLoading;

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={t('dashboard')} />
            <div className="flex h-full flex-1 flex-col gap-4 overflow-x-auto p-4">
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
                            {isLoading ? t('loading') : displayDate(selectedDate)}
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
                            <span className="text-sm font-semibold text-zinc-700">{tokenUnitLabel(tokenCount)}</span>
                        </div>
                    </div>
                </div>
                <StatusBanner message={null} error={errorMessage} />

                <div className="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
                    {terrains.map((terrain) => (
                        <Card
                            key={terrain.id}
                            className="group h-full overflow-hidden rounded-2xl border-border/60 bg-card/95 py-0 shadow-sm transition-all duration-200 hover:-translate-y-0.5 hover:shadow-lg hover:shadow-black/10 dark:hover:shadow-black/30"
                        >
                            <div
                                className="relative flex h-24 w-full items-end overflow-hidden px-4 pb-3"
                                style={{ backgroundImage: imageStyleForTerrain(terrain.id) }}
                            >
                                <div className="pointer-events-none absolute -top-8 -right-8 h-24 w-24 rounded-full bg-white/20 blur-xl" />
                                <div className="pointer-events-none absolute -bottom-10 -left-4 h-20 w-28 rounded-full bg-black/15 blur-xl" />
                                <div className="absolute inset-0 bg-gradient-to-t from-black/65 via-black/25 to-transparent" />
                                <Badge
                                    variant="secondary"
                                    className="absolute top-2 right-2 border-white/20 bg-black/35 text-[11px] text-white backdrop-blur-sm"
                                >
                                    {t('free')}: {terrain.available_slots_count}
                                </Badge>
                                <h3 className="relative text-5xl font-black tracking-tight text-white drop-shadow-md">
                                    {terrain.name}
                                </h3>
                            </div>
                            <CardContent className="flex-1 space-y-2 px-5 pb-3">
                                {terrain.slots.length === 0 ? (
                                    <p className="text-base text-muted-foreground">
                                        {t('no_free_slots_selected_date')}
                                    </p>
                                ) : (
                                    <div className="flex max-h-56 flex-wrap gap-2 overflow-y-auto pr-1">
                                        {terrain.slots.map((slot) => (
                                            <Card
                                                key={slot.id}
                                                className="w-fit shrink-0 border-emerald-200/70 bg-emerald-50/70 py-0 shadow-sm dark:border-emerald-900/60 dark:bg-emerald-950/30"
                                            >
                                                <CardContent className="px-3 py-2 text-center text-sm font-medium whitespace-nowrap text-emerald-900 sm:text-base dark:text-emerald-100">
                                                    {toTime(slot.starts_at)} - {toTime(slot.ends_at)}
                                                </CardContent>
                                            </Card>
                                        ))}
                                    </div>
                                )}
                            </CardContent>
                            <CardFooter className="mt-auto px-5 pt-2 pb-5">
                                <Button
                                    asChild
                                    className="h-10 w-full rounded-xl text-base font-semibold shadow-sm"
                                >
                                    <Link
                                        href={dashboardRoutes.terrains.show(
                                            { terrain: terrain.id },
                                            { query: { date: selectedDate } },
                                        )}
                                    >
                                        {t('open_terrain')}
                                    </Link>
                                </Button>
                            </CardFooter>
                        </Card>
                    ))}

                    {terrains.length === 0 && (
                        <Card className="md:col-span-2 xl:col-span-3">
                            <CardContent>
                                <p className="text-sm text-muted-foreground">
                                    {t('no_active_terrains_available')}
                                </p>
                            </CardContent>
                        </Card>
                    )}
                </div>
            </div>
        </AppLayout>
    );
}
