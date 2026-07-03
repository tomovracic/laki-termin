import { Head, Link, router } from '@inertiajs/react';
import type { FormEvent } from 'react';
import { useState } from 'react';
import { AdminSectionLayout } from '@/components/admin/admin-section-layout';
import { ReportPagination } from '@/components/admin/report-pagination';
import type { LoginReportEntry, PaginatedResponse, ReportFilterUser } from '@/components/admin/report-types';
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

type LoginReportFilters = {
    from_date: string | null;
    to_date: string | null;
    user_id: number | null;
    search: string | null;
};

type AdminLoginReportPageProps = {
    logs: PaginatedResponse<LoginReportEntry>;
    users: ReportFilterUser[];
    filters: LoginReportFilters;
};

export default function AdminLoginReportPage({ logs, users, filters }: AdminLoginReportPageProps) {
    const { locale, t } = useI18n();
    const [fromDate, setFromDate] = useState(filters.from_date ?? '');
    const [toDate, setToDate] = useState(filters.to_date ?? '');
    const [userId, setUserId] = useState(filters.user_id ? String(filters.user_id) : 'all');
    const [search, setSearch] = useState(filters.search ?? '');

    function applyFilters(event?: FormEvent) {
        event?.preventDefault();

        router.get(
            '/admin/reports/logins',
            {
                from_date: fromDate || undefined,
                to_date: toDate || undefined,
                user_id: userId !== 'all' ? userId : undefined,
                search: search.trim() || undefined,
            },
            { preserveState: true, preserveScroll: true },
        );
    }

    function formatDateTime(value: string): string {
        return new Date(value).toLocaleString(locale === 'hr' ? 'hr-HR' : 'en-GB', {
            day: '2-digit',
            month: '2-digit',
            year: 'numeric',
            hour: '2-digit',
            minute: '2-digit',
        });
    }

    return (
        <AdminSectionLayout
            title={t('report_logins_title')}
            description={t('report_logins_description')}
        >
            <Head title={t('report_logins_title')} />

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
                    <form className="grid gap-4 md:grid-cols-2 lg:grid-cols-4" onSubmit={applyFilters}>
                        <div className="space-y-2">
                            <Label htmlFor="from_date">{t('from')}</Label>
                            <Input
                                id="from_date"
                                type="date"
                                value={fromDate}
                                onChange={(event) => setFromDate(event.target.value)}
                            />
                        </div>
                        <div className="space-y-2">
                            <Label htmlFor="to_date">{t('to')}</Label>
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
                            <Label htmlFor="search">{t('report_search_user')}</Label>
                            <Input
                                id="search"
                                value={search}
                                onChange={(event) => setSearch(event.target.value)}
                                placeholder={t('search_users_placeholder')}
                            />
                        </div>
                        <div className="flex items-end md:col-span-2 lg:col-span-4">
                            <Button type="submit">{t('report_apply_filters')}</Button>
                        </div>
                    </form>
                </CardContent>
            </Card>

            <Card>
                <CardHeader className="flex flex-row items-center justify-between">
                    <CardTitle>{t('report_logins_title')}</CardTitle>
                    <span className="text-sm text-muted-foreground">
                        {t('report_total')}: {logs.meta?.total ?? logs.data.length}
                    </span>
                </CardHeader>
                <CardContent className="space-y-4">
                    {logs.data.length === 0 ? (
                        <p className="text-sm text-muted-foreground">{t('report_no_results')}</p>
                    ) : (
                        <div className="overflow-x-auto rounded-lg border">
                            <table className="w-full min-w-[640px] text-sm">
                                <thead className="bg-muted/50">
                                    <tr>
                                        <th className="px-4 py-3 text-left font-medium">{t('report_user')}</th>
                                        <th className="px-4 py-3 text-left font-medium">{t('report_login_time')}</th>
                                        <th className="px-4 py-3 text-left font-medium">{t('report_ip_address')}</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {logs.data.map((log) => (
                                        <tr key={log.id} className="border-t">
                                            <td className="px-4 py-3">
                                                <div className="font-medium">{log.user?.name ?? '—'}</div>
                                                <div className="text-xs text-muted-foreground">{log.user?.email}</div>
                                            </td>
                                            <td className="px-4 py-3">{formatDateTime(log.logged_in_at)}</td>
                                            <td className="px-4 py-3 text-muted-foreground">{log.ip_address ?? '—'}</td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    )}
                    <ReportPagination
                        meta={logs.meta}
                        routePath="/admin/reports/logins"
                        query={{
                            from_date: filters.from_date,
                            to_date: filters.to_date,
                            user_id: filters.user_id,
                            search: filters.search,
                        }}
                    />
                </CardContent>
            </Card>
        </AdminSectionLayout>
    );
}
