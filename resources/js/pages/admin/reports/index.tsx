import { Head, Link } from '@inertiajs/react';
import { CalendarX, ClipboardList, LogIn } from 'lucide-react';
import { AdminSectionLayout } from '@/components/admin/admin-section-layout';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { useI18n } from '@/lib/i18n';

const reportLinks = [
    {
        href: '/admin/reports/logins',
        titleKey: 'report_logins_title' as const,
        descriptionKey: 'report_logins_description' as const,
        icon: LogIn,
    },
    {
        href: '/admin/reports/reserved',
        titleKey: 'report_reserved_title' as const,
        descriptionKey: 'report_reserved_description' as const,
        icon: ClipboardList,
    },
    {
        href: '/admin/reports/cancelled',
        titleKey: 'report_cancelled_title' as const,
        descriptionKey: 'report_cancelled_description' as const,
        icon: CalendarX,
    },
];

export default function AdminReportsOverviewPage() {
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
                            <CardContent>
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
