import { Head, Link, router } from '@inertiajs/react';
import type { FormEvent } from 'react';
import { useState } from 'react';
import { AdminSectionLayout } from '@/components/admin/admin-section-layout';
import { ReportPagination } from '@/components/admin/report-pagination';
import { ReportReservationsTable } from '@/components/admin/report-reservations-table';
import type {
    PaginatedResponse,
    ReportFilterTerrain,
    ReportFilterUser,
    ReportReservation,
} from '@/components/admin/report-types';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
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

type CancelledReportFilters = {
    from_date: string | null;
    to_date: string | null;
    user_id: number | null;
    terrain_id: number | null;
};

type AdminCancelledReportPageProps = {
    reservations: PaginatedResponse<ReportReservation>;
    users: ReportFilterUser[];
    terrains: ReportFilterTerrain[];
    filters: CancelledReportFilters;
};

export default function AdminCancelledReportPage({
    reservations,
    users,
    terrains,
    filters,
}: AdminCancelledReportPageProps) {
    const { t } = useI18n();
    const [fromDate, setFromDate] = useState(filters.from_date ?? '');
    const [toDate, setToDate] = useState(filters.to_date ?? '');
    const [userId, setUserId] = useState(filters.user_id ? String(filters.user_id) : 'all');
    const [terrainId, setTerrainId] = useState(filters.terrain_id ? String(filters.terrain_id) : 'all');

    function applyFilters(event?: FormEvent) {
        event?.preventDefault();

        router.get(
            '/admin/reports/cancelled',
            {
                from_date: fromDate || undefined,
                to_date: toDate || undefined,
                user_id: userId !== 'all' ? userId : undefined,
                terrain_id: terrainId !== 'all' ? terrainId : undefined,
            },
            { preserveState: true, preserveScroll: true },
        );
    }

    return (
        <AdminSectionLayout
            title={t('report_cancelled_title')}
            description={t('report_cancelled_description')}
        >
            <Head title={t('report_cancelled_title')} />

            <div className="flex flex-wrap gap-2">
                <Button variant="outline" size="sm" asChild>
                    <Link href="/admin/reports">{t('reports_overview')}</Link>
                </Button>
            </div>

            <Card>
                <CardHeader>
                    <CardTitle>{t('report_filters')}</CardTitle>
                </CardHeader>
                <CardContent>
                    <form className="grid gap-4 md:grid-cols-2 lg:grid-cols-3" onSubmit={applyFilters}>
                        <div className="space-y-2">
                            <Label htmlFor="from_date">{t('report_cancelled_from')}</Label>
                            <Input
                                id="from_date"
                                type="date"
                                value={fromDate}
                                onChange={(event) => setFromDate(event.target.value)}
                            />
                        </div>
                        <div className="space-y-2">
                            <Label htmlFor="to_date">{t('report_cancelled_to')}</Label>
                            <Input
                                id="to_date"
                                type="date"
                                value={toDate}
                                onChange={(event) => setToDate(event.target.value)}
                            />
                        </div>
                        <div className="space-y-2">
                            <Label>{t('report_user')}</Label>
                            <Select value={userId} onValueChange={setUserId}>
                                <SelectTrigger>
                                    <SelectValue placeholder={t('report_all_users')} />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="all">{t('report_all_users')}</SelectItem>
                                    {users.map((user) => (
                                        <SelectItem key={user.id} value={String(user.id)}>
                                            {user.name}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                        </div>
                        <div className="space-y-2">
                            <Label>{t('terrain')}</Label>
                            <Select value={terrainId} onValueChange={setTerrainId}>
                                <SelectTrigger>
                                    <SelectValue placeholder={t('all_terrains')} />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="all">{t('all_terrains')}</SelectItem>
                                    {terrains.map((terrain) => (
                                        <SelectItem key={terrain.id} value={String(terrain.id)}>
                                            {terrain.name}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                        </div>
                        <div className="flex items-end">
                            <Button type="submit">{t('report_apply_filters')}</Button>
                        </div>
                    </form>
                </CardContent>
            </Card>

            <Card>
                <CardHeader className="flex flex-row items-center justify-between">
                    <CardTitle>{t('report_cancelled_title')}</CardTitle>
                    <span className="text-sm text-muted-foreground">
                        {t('report_total')}: {reservations.meta?.total ?? reservations.data.length}
                    </span>
                </CardHeader>
                <CardContent className="space-y-4">
                    <ReportReservationsTable reservations={reservations.data} showCancelledAt />
                    <ReportPagination
                        meta={reservations.meta}
                        routePath="/admin/reports/cancelled"
                        query={{
                            from_date: filters.from_date,
                            to_date: filters.to_date,
                            user_id: filters.user_id,
                            terrain_id: filters.terrain_id,
                        }}
                    />
                </CardContent>
            </Card>
        </AdminSectionLayout>
    );
}
