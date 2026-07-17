import { Head, router } from '@inertiajs/react';
import type { LeagueSummary } from '@/components/league/types';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import AppLayout from '@/layouts/app-layout';
import { useI18n } from '@/lib/i18n';
import { dashboard } from '@/routes';
import type { BreadcrumbItem } from '@/types';

type UserLeaguesPageProps = {
    leagues: LeagueSummary[];
};

function roundsLabel(rounds: number, t: (key: string) => string): string {
    if (rounds === 1) {
        return t('league_rounds_once');
    }

    if (rounds === 2) {
        return t('league_rounds_twice');
    }

    if (rounds === 3) {
        return t('league_rounds_thrice');
    }

    return t('league_rounds_count').replace('{count}', `${rounds}`);
}

export default function UserLeaguesPage({ leagues }: UserLeaguesPageProps) {
    const { t } = useI18n();

    const breadcrumbs: BreadcrumbItem[] = [
        { title: t('dashboard'), href: dashboard() },
        { title: t('leagues'), href: '/dashboard/leagues' },
    ];

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={t('leagues')} />
            <div className="flex h-full flex-1 flex-col gap-4 overflow-x-auto p-4">
                <div>
                    <h1 className="text-2xl font-semibold tracking-tight">{t('leagues')}</h1>
                    <p className="mt-1 text-sm text-muted-foreground">{t('leagues_user_description')}</p>
                </div>

                <div className="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
                    {leagues.map((league) => (
                        <Card key={league.id}>
                            <CardHeader>
                                <CardTitle className="text-lg">{league.name}</CardTitle>
                            </CardHeader>
                            <CardContent className="space-y-3">
                                <div className="flex flex-wrap gap-2">
                                    <Badge variant="outline">
                                        {league.format === 'knockout'
                                            ? t('tournament_format_knockout')
                                            : roundsLabel(league.rounds, t)}
                                    </Badge>
                                    {league.format === 'knockout' && league.sets_best_of ? (
                                        <Badge variant="outline">
                                            {t('tournament_best_of').replace(
                                                '{count}',
                                                `${league.sets_best_of}`,
                                            )}
                                        </Badge>
                                    ) : null}
                                    <Badge variant="secondary">
                                        {league.participants_count} {t('league_participants').toLowerCase()}
                                    </Badge>
                                </div>
                                <p className="text-sm text-muted-foreground">
                                    {league.played_matches_count}/{league.matches_count}{' '}
                                    {t('league_matches_played').toLowerCase()}
                                </p>
                                <Button
                                    variant="outline"
                                    className="w-full"
                                    onClick={() => router.visit(`/dashboard/leagues/${league.id}`)}
                                >
                                    {t('league_view')}
                                </Button>
                            </CardContent>
                        </Card>
                    ))}
                </div>

                {leagues.length === 0 && (
                    <p className="text-sm text-muted-foreground">{t('league_no_leagues')}</p>
                )}
            </div>
        </AppLayout>
    );
}
