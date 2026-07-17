import { CalendarDays } from 'lucide-react';
import {
    formatPlayedAtDate,
    getSetScores,
    MatchScoreboard,
    type MatchDisplayPlayer,
} from '@/components/match/match-scoreboard';
import type { LeagueMatch, LeagueMatchPlayer } from '@/components/league/types';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { useI18n } from '@/lib/i18n';
import { cn } from '@/lib/utils';

type LeagueMatchesListProps = {
    matches: LeagueMatch[];
    currentUserId?: number | null;
    filterUserId?: number | null;
    onEnterResult?: (match: LeagueMatch) => void;
};

function toDisplayPlayer(player: LeagueMatchPlayer | null): MatchDisplayPlayer {
    return {
        userId: player?.id ?? null,
        name: player?.name ?? 'TBD',
        avatar: player?.avatar,
    };
}

function matchInvolvesUser(match: LeagueMatch, userId: number): boolean {
    return match.player_one?.id === userId || match.player_two?.id === userId;
}

function LeagueMatchMetadata({
    match,
    locale,
    t,
}: {
    match: LeagueMatch;
    locale: string;
    t: (key: string) => string;
}) {
    const playedAtDate = formatPlayedAtDate(match.played_at ?? null, locale);

    return (
        <div className="flex flex-wrap items-center gap-2 border-t border-border/60 pt-2">
            <Badge variant={match.status === 'played' ? 'default' : 'secondary'}>
                {match.status === 'played' ? t('league_played') : t('league_pending')}
            </Badge>
            <Badge variant="outline">
                {t('league_round')} {match.round}
            </Badge>
            {playedAtDate !== null ? (
                <Badge
                    variant="outline"
                    className="gap-1.5 font-normal text-muted-foreground tabular-nums"
                >
                    <CalendarDays className="size-3 shrink-0" aria-hidden />
                    <span>{playedAtDate}</span>
                </Badge>
            ) : (
                <Badge variant="outline" className="font-normal text-muted-foreground">
                    —
                </Badge>
            )}
        </div>
    );
}

export function LeagueMatchesList({
    matches,
    currentUserId,
    filterUserId,
    onEnterResult,
}: LeagueMatchesListProps) {
    const { t, locale } = useI18n();

    const visibleMatches =
        filterUserId !== undefined && filterUserId !== null
            ? matches.filter((match) => matchInvolvesUser(match, filterUserId))
            : matches;

    if (visibleMatches.length === 0) {
        return <p className="text-sm text-muted-foreground">{t('league_no_matches')}</p>;
    }

    return (
        <div className="space-y-3">
            {visibleMatches.map((match) => {
                const setScores = getSetScores(match);
                const involvesCurrentUser =
                    currentUserId !== undefined &&
                    currentUserId !== null &&
                    matchInvolvesUser(match, currentUserId);

                return (
                    <Card
                        key={match.id}
                        className={cn(
                            'gap-0 py-0',
                            involvesCurrentUser && 'border-primary/40 bg-primary/5',
                        )}
                    >
                        <CardContent className="space-y-2 p-3">
                            <div className="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                                <MatchScoreboard
                                    playerOne={toDisplayPlayer(match.player_one)}
                                    playerTwo={toDisplayPlayer(match.player_two)}
                                    sets={setScores}
                                    highlightUserId={currentUserId}
                                />
                                {onEnterResult && (
                                    <div className="flex shrink-0 gap-2 self-end sm:self-auto">
                                        <Button
                                            type="button"
                                            variant="outline"
                                            size="sm"
                                            onClick={() => onEnterResult(match)}
                                        >
                                            {match.status === 'played'
                                                ? t('league_edit_result')
                                                : t('league_enter_result')}
                                        </Button>
                                    </div>
                                )}
                            </div>
                            <LeagueMatchMetadata match={match} locale={locale} t={t} />
                        </CardContent>
                    </Card>
                );
            })}
        </div>
    );
}
