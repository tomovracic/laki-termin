import { Head, usePage } from '@inertiajs/react';
import { EloRankingTable } from '@/components/ranking/elo-ranking-table';
import type { EloRankingEntry } from '@/components/ranking/types';
import AppLayout from '@/layouts/app-layout';
import { useI18n } from '@/lib/i18n';
import { dashboard } from '@/routes';
import type { Auth } from '@/types/auth';
import type { BreadcrumbItem } from '@/types';

type RankingPageProps = {
    rankings: EloRankingEntry[];
};

export default function RankingPage({ rankings }: RankingPageProps) {
    const { t } = useI18n();
    const { auth } = usePage<{ auth: Auth }>().props;
    const currentUserId = auth.user?.id ?? null;

    const breadcrumbs: BreadcrumbItem[] = [
        { title: t('dashboard'), href: dashboard() },
        { title: t('ranking'), href: '/dashboard/ranking' },
    ];

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={t('ranking')} />
            <div className="flex h-full flex-1 flex-col gap-4 overflow-x-auto p-4">
                <div>
                    <h1 className="text-2xl font-semibold tracking-tight">{t('ranking')}</h1>
                    <p className="text-sm text-muted-foreground">{t('ranking_description')}</p>
                </div>

                <EloRankingTable rankings={rankings} highlightUserId={currentUserId} />
            </div>
        </AppLayout>
    );
}
