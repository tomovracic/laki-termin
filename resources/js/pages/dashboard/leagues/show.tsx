import { Head, usePage } from '@inertiajs/react';
import { LeagueMatchesSection } from '@/components/league/league-matches-section';
import { LeagueStandingsTable } from '@/components/league/league-standings-table';
import { TournamentBracket } from '@/components/league/tournament-bracket';
import type {
    KnockoutChampion,
    LeagueDetail,
    LeagueMatch,
    LeagueStandingsEntry,
} from '@/components/league/types';
import { Badge } from '@/components/ui/badge';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import AppLayout from '@/layouts/app-layout';
import { useI18n } from '@/lib/i18n';
import { dashboard } from '@/routes';
import type { Auth } from '@/types/auth';
import type { BreadcrumbItem } from '@/types';

type UserLeagueShowPageProps = {
    league: LeagueDetail;
    standings: LeagueStandingsEntry[];
    matches: LeagueMatch[];
    knockout_champion?: KnockoutChampion | null;
};

export default function UserLeagueShowPage({
    league,
    standings,
    matches,
    knockout_champion = null,
}: UserLeagueShowPageProps) {
    const { t } = useI18n();
    const { auth } = usePage<{ auth: Auth }>().props;
    const currentUserId = auth.user?.id ?? null;
    const isKnockout = league.format === 'knockout';
    const bestOf = league.sets_best_of ?? 3;

    const breadcrumbs: BreadcrumbItem[] = [
        { title: t('dashboard'), href: dashboard() },
        { title: t('leagues'), href: '/dashboard/leagues' },
        { title: league.name, href: `/dashboard/leagues/${league.id}` },
    ];

    const isParticipant = standings.some((entry) => entry.user_id === currentUserId);

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={league.name} />
            <div className="flex h-full flex-1 flex-col gap-4 overflow-x-auto p-4">
                <div>
                    <h1 className="text-2xl font-semibold tracking-tight">{league.name}</h1>
                    <div className="mt-2 flex flex-wrap gap-2">
                        <Badge variant="outline">
                            {isKnockout
                                ? t('tournament_format_knockout')
                                : `${league.participants_count} ${t('league_participants').toLowerCase()}`}
                        </Badge>
                        {isKnockout && (
                            <Badge variant="outline">
                                {t('tournament_best_of').replace('{count}', `${bestOf}`)}
                            </Badge>
                        )}
                        {knockout_champion && (
                            <Badge variant="default">
                                {t('tournament_champion')}: {knockout_champion.name}
                            </Badge>
                        )}
                        <Badge variant="secondary">
                            {league.played_matches_count}/{league.matches_count}{' '}
                            {t('league_matches_played').toLowerCase()}
                        </Badge>
                    </div>
                </div>

                {isKnockout ? (
                    <Card>
                        <CardHeader>
                            <CardTitle>{t('tournament_bracket')}</CardTitle>
                        </CardHeader>
                        <CardContent>
                            <TournamentBracket
                                matches={matches}
                                currentUserId={currentUserId}
                                currentBracketRound={league.current_bracket_round ?? null}
                                canFinishRound={Boolean(league.can_finish_round)}
                                nextRoundPending={Boolean(league.next_round_pending)}
                                championName={knockout_champion?.name ?? null}
                            />
                        </CardContent>
                    </Card>
                ) : (
                    <>
                        <Card>
                            <CardHeader>
                                <CardTitle>{t('league_standings')}</CardTitle>
                            </CardHeader>
                            <CardContent>
                                <LeagueStandingsTable
                                    standings={standings}
                                    highlightUserId={currentUserId}
                                />
                            </CardContent>
                        </Card>

                        <LeagueMatchesSection
                            matches={matches}
                            standings={standings}
                            currentUserId={currentUserId}
                            isParticipant={isParticipant}
                        />
                    </>
                )}
            </div>
        </AppLayout>
    );
}
