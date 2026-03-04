import { usePage } from '@inertiajs/react';
import AppLayout from '@/layouts/app-layout';
import { useI18n } from '@/lib/i18n';
import type { BreadcrumbItem } from '@/types';

type AdminSectionLayoutProps = {
    title: string;
    description: string;
    showHeader?: boolean;
    children: React.ReactNode;
};

function isActivePath(currentUrl: string, target: string): boolean {
    const pathname = currentUrl.split('?')[0];
    return pathname === target;
}

export function AdminSectionLayout({
    title,
    description,
    showHeader = true,
    children,
}: AdminSectionLayoutProps) {
    const { t } = useI18n();
    const page = usePage();
    const currentUrl = page.url;
    const isAdminUsersPage = isActivePath(currentUrl, '/admin/users');
    const isAdminTerrainsPage = isActivePath(currentUrl, '/admin/terrains');
    const pageTitle = isAdminUsersPage
        ? t('users_overview')
        : isAdminTerrainsPage
          ? t('terrains_overview')
          : title;
    const breadcrumbs: BreadcrumbItem[] = [
        {
            title: t('admin'),
            href: '/admin/users',
        },
    ];

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <div className="flex h-full flex-1 flex-col gap-4 overflow-x-auto rounded-xl p-4">
                {showHeader && (
                    <div className="rounded-xl border bg-gradient-to-br from-muted/40 via-background to-background p-5">
                        <h1 className="text-2xl font-semibold tracking-tight">{pageTitle}</h1>
                        <p className="mt-1 text-sm text-muted-foreground">{description}</p>
                    </div>
                )}

                <section className="space-y-4">{children}</section>
            </div>
        </AppLayout>
    );
}
