import { Head, usePage } from '@inertiajs/react';
import { useState } from 'react';
import { EloRankingTable } from '@/components/ranking/elo-ranking-table';
import type { EloRankingGroupSection } from '@/components/ranking/types';
import { ToggleGroup, ToggleGroupItem } from '@/components/ui/toggle-group';
import AppLayout from '@/layouts/app-layout';
import { useI18n } from '@/lib/i18n';
import { dashboard } from '@/routes';
import type { Auth } from '@/types/auth';
import type { BreadcrumbItem } from '@/types';

type RankingPageProps = {
    groups: EloRankingGroupSection[];
};

export default function RankingPage({ groups }: RankingPageProps) {
    const { t } = useI18n();
    const { auth } = usePage<{ auth: Auth }>().props;
    const currentUserId = auth.user?.id ?? null;
    const [activeGroupId, setActiveGroupId] = useState(
        groups[0] ? String(groups[0].id) : '',
    );

    const activeGroup =
        groups.find((group) => String(group.id) === activeGroupId) ?? groups[0] ?? null;

    const breadcrumbs: BreadcrumbItem[] = [
        { title: t('dashboard'), href: dashboard() },
        { title: t('ranking'), href: '/dashboard/ranking' },
    ];

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={t('ranking')} />
            <div className="flex h-full flex-1 flex-col gap-6 p-4">
                <div>
                    <h1 className="text-2xl font-semibold tracking-tight">{t('ranking')}</h1>
                    <p className="text-sm text-muted-foreground">{t('ranking_description')}</p>
                </div>

                {groups.length === 0 || activeGroup === null ? (
                    <p className="text-sm text-muted-foreground">{t('ranking_empty_groups')}</p>
                ) : (
                    <div className="space-y-4">
                        <ToggleGroup
                            type="single"
                            variant="outline"
                            value={String(activeGroup.id)}
                            onValueChange={(value) => {
                                if (value !== '') {
                                    setActiveGroupId(value);
                                }
                            }}
                            className="flex flex-wrap justify-start gap-0"
                        >
                            {groups.map((group) => (
                                <ToggleGroupItem
                                    key={group.id}
                                    value={String(group.id)}
                                    aria-label={group.name}
                                    className="gap-2 px-3"
                                >
                                    <span
                                        className="inline-block h-2.5 w-2.5 shrink-0 rounded-full"
                                        style={{ backgroundColor: group.color_hex }}
                                    />
                                    {group.name}
                                </ToggleGroupItem>
                            ))}
                        </ToggleGroup>

                        <EloRankingTable
                            rankings={activeGroup.rankings}
                            highlightUserId={currentUserId}
                        />
                    </div>
                )}
            </div>
        </AppLayout>
    );
}
