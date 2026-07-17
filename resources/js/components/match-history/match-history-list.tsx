import { Link } from '@inertiajs/react';
import { CalendarDays, Pencil, Trash2 } from 'lucide-react';
import type { MatchHistoryEntry } from '@/components/match-history/types';
import {
    formatPlayedAtDate,
    getSetScores,
    MatchScoreboard,
    type MatchDisplayPlayer,
} from '@/components/match/match-scoreboard';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { useI18n } from '@/lib/i18n';
import { cn } from '@/lib/utils';

type MatchHistoryListProps = {
    matches: MatchHistoryEntry[];
    currentUserId: number | null;
    deletingMatchId?: string | null;
    onEdit?: (match: MatchHistoryEntry) => void;
    onDelete?: (match: MatchHistoryEntry) => void;
};

function toDisplayPlayer(player: MatchHistoryEntry['player_one']): MatchDisplayPlayer {
    return {
        userId: player.user_id,
        name: player.name,
        avatar: player.avatar,
    };
}

function MatchMetadata({
    match,
    locale,
    t,
}: {
    match: MatchHistoryEntry;
    locale: string;
    t: (key: string) => string;
}) {
    const playedAtDate = formatPlayedAtDate(match.played_at, locale);

    return (
        <div className="flex flex-wrap items-center gap-2 border-t border-border/60 pt-2">
            <Badge variant={match.source === 'league' ? 'default' : 'secondary'}>
                {match.source === 'league' ? t('match_history_league') : t('match_history_casual')}
            </Badge>
            {match.league !== null && (
                <Badge variant="outline" asChild>
                    <Link href={`/dashboard/leagues/${match.league.id}`}>
                        {match.league.name} — {t('league_round')} {match.league.round}
                    </Link>
                </Badge>
            )}
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

function matchInvolvesUser(match: MatchHistoryEntry, userId: number): boolean {
    return match.player_one.user_id === userId || match.player_two.user_id === userId;
}

export function MatchHistoryList({
    matches,
    currentUserId,
    deletingMatchId = null,
    onEdit,
    onDelete,
}: MatchHistoryListProps) {
    const { t, locale } = useI18n();

    if (matches.length === 0) {
        return <p className="text-sm text-muted-foreground">{t('match_history_no_matches')}</p>;
    }

    return (
        <div className="space-y-3">
            {matches.map((match) => {
                const involvesCurrentUser =
                    currentUserId !== null && matchInvolvesUser(match, currentUserId);
                const setScores = getSetScores(match);
                const showActions = match.can_edit || match.can_delete;

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
                                {showActions && (
                                    <div className="flex shrink-0 gap-2 self-end sm:self-auto">
                                        {match.can_edit && onEdit && (
                                            <Button
                                                type="button"
                                                variant="outline"
                                                size="sm"
                                                onClick={() => onEdit(match)}
                                            >
                                                <Pencil className="size-4" />
                                                {t('edit')}
                                            </Button>
                                        )}
                                        {match.can_delete && onDelete && (
                                            <Button
                                                type="button"
                                                variant="outline"
                                                size="sm"
                                                disabled={deletingMatchId === match.id}
                                                onClick={() => onDelete(match)}
                                            >
                                                <Trash2 className="size-4" />
                                                {t('remove')}
                                            </Button>
                                        )}
                                    </div>
                                )}
                            </div>
                            <MatchMetadata match={match} locale={locale} t={t} />
                        </CardContent>
                    </Card>
                );
            })}
        </div>
    );
}
