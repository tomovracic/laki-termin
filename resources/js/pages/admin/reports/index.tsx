import { Head, Link } from '@inertiajs/react';
import { CalendarX, ClipboardList, LogIn } from 'lucide-react';
import { AdminSectionLayout } from '@/components/admin/admin-section-layout';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { useI18n } from '@/lib/i18n';

type ReportStats = {
    reserved_count: number;
    cancelled_count: number;
};

type AdminReportsOverviewPageProps = {
    stats: ReportStats;
};

const reportLinks = [
    {
        href: '/admin/reports/logins',
        titleKey: 'report_logins_title' as const,
        descriptionKey: 'report_logins_description' as const,
        icon: LogIn,
        countKey: null,
    },
    {
        href: '/admin/reports/reserved',
        titleKey: 'report_reserved_title' as const,
        descriptionKey: 'report_reserved_description' as const,
        icon: ClipboardList,
        countKey: 'reserved_count' as const,
    },
    {
        href: '/admin/reports/cancelled',
        titleKey: 'report_cancelled_title' as const,
        descriptionKey: 'report_cancelled_description' as const,
        icon: CalendarX,
        countKey: 'cancelled_count' as const,
    },
];

export default function AdminReportsOverviewPage({ stats }: AdminReportsOverviewPageProps) {
    const { t } = useI18n();

    return (
        <AdminSectionLayout
            title={t('reports_overview')}
            description={t('reports_overview_description')}
        >
            <Head title={t('reports_overview')} />

            <div className="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
                {reportLinks.map((report) => {
                    const Icon = report.icon;

                    return (
                        <Card key={report.href}>
                            <CardHeader>
                                <div className="flex items-center gap-3">
                                    <div className="rounded-lg bg-muted p-2">
                                        <Icon className="size-5" />
                                    </div>
                                    <div>
                                        <CardTitle>{t(report.titleKey)}</CardTitle>
                                        <CardDescription className="mt-1">
                                            {t(report.descriptionKey)}
                                        </CardDescription>
                                    </div>
                                </div>
                            </CardHeader>
                            <CardContent className="space-y-4">
                                {report.countKey !== null && (
                                    <div>
                                        <p className="text-sm text-muted-foreground">{t('report_total')}</p>
                                        <p className="text-3xl font-semibold tracking-tight">
                                            {stats[report.countKey]}
                                        </p>
                                    </div>
                                )}
                                <Button asChild>
                                    <Link href={report.href}>{t('report_open')}</Link>
                                </Button>
                            </CardContent>
                        </Card>
                    );
                })}
            </div>
        </AdminSectionLayout>
    );
}
