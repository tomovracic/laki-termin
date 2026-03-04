import { Head, Link } from '@inertiajs/react';
import { ChevronLeft, ChevronRight } from 'lucide-react';
import { useState } from 'react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardFooter, CardHeader } from '@/components/ui/card';
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
        const hueA = (terrainId * 31) % 360;
        const hueB = (terrainId * 53 + 120) % 360;

        return `linear-gradient(135deg, hsl(${hueA} 75% 55%), hsl(${hueB} 70% 45%))`;
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
                <div className="flex flex-wrap items-center gap-2">
                    <div className="inline-flex items-center gap-1 rounded-md border border-border/70 bg-background p-1">
                        <Button
                            type="button"
                            variant="ghost"
                            size="icon"
                            disabled={isPreviousDisabled}
                            onClick={() => void handleDateStep(-1)}
                        >
                            <ChevronLeft className="size-4" />
                        </Button>
                        <span className="min-w-44 text-center text-sm font-medium">
                            {isLoading ? t('loading') : displayDate(selectedDate)}
                        </span>
                        <Button
                            type="button"
                            variant="ghost"
                            size="icon"
                            disabled={isNextDisabled}
                            onClick={() => void handleDateStep(1)}
                        >
                            <ChevronRight className="size-4" />
                        </Button>
                    </div>
                    <Badge variant="secondary">{t('available_tokens')}: {tokenCount}</Badge>
                </div>
                {errorMessage !== null && (
                    <p className="text-sm text-red-500">{errorMessage}</p>
                )}

                <div className="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
                    {terrains.map((terrain) => (
                        <Card
                            key={terrain.id}
                            className="h-full overflow-hidden border-border/70 py-0"
                        >
                            <div
                                className="relative flex h-32 w-full items-end p-4"
                                style={{ backgroundImage: imageStyleForTerrain(terrain.id) }}
                            >
                                <div className="absolute inset-0 bg-gradient-to-t from-black/55 via-black/20 to-transparent" />
                                <h3 className="relative text-2xl font-bold tracking-tight text-white drop-shadow-md">
                                    {terrain.name}
                                </h3>
                            </div>
                            <CardHeader>
                                <div className="flex items-start justify-between gap-2">
                                    <div>
                                        <CardDescription>{terrain.description ?? terrain.code}</CardDescription>
                                    </div>
                                    <Badge variant="outline">
                                        {t('free')}: {terrain.available_slots_count}
                                    </Badge>
                                </div>
                            </CardHeader>
                            <CardContent className="flex-1 space-y-2">
                                {terrain.slots.length === 0 ? (
                                    <p className="text-sm text-muted-foreground">
                                        {t('no_free_slots_selected_date')}
                                    </p>
                                ) : (
                                    <div className="grid max-h-56 grid-cols-3 gap-2 overflow-y-auto pr-1">
                                        {terrain.slots.map((slot) => (
                                            <Card
                                                key={slot.id}
                                                className="border-emerald-200/70 bg-emerald-50/70 py-0 shadow-sm dark:border-emerald-900/60 dark:bg-emerald-950/30"
                                            >
                                                <CardContent className="px-3 py-2 text-center text-xs font-medium text-emerald-900 dark:text-emerald-100">
                                                    {toTime(slot.starts_at)} - {toTime(slot.ends_at)}
                                                </CardContent>
                                            </Card>
                                        ))}
                                    </div>
                                )}
                            </CardContent>
                            <CardFooter className="mt-auto px-6 pb-4">
                                <Button asChild className="w-full">
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
