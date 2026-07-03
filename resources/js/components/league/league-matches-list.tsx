import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import type { LeagueMatch, LeagueMatchPlayer } from '@/components/league/types';
import { useInitials } from '@/hooks/use-initials';
import { useI18n } from '@/lib/i18n';
import { cn } from '@/lib/utils';

type LeagueMatchesListProps = {
    matches: LeagueMatch[];
    currentUserId?: number | null;
    filterUserId?: number | null;
    perspectiveUserId?: number | null;
    onEnterResult?: (match: LeagueMatch) => void;
};

function formatScore(match: LeagueMatch): string {
    if (match.status !== 'played') {
        return '—';
    }

    const sets: string[] = [];

    if (match.set1_player_one_games !== null && match.set1_player_two_games !== null) {
        sets.push(`${match.set1_player_one_games}-${match.set1_player_two_games}`);
    }

    if (match.set2_player_one_games !== null && match.set2_player_two_games !== null) {
        sets.push(`${match.set2_player_one_games}-${match.set2_player_two_games}`);
    }

    if (match.set3_player_one_games !== null && match.set3_player_two_games !== null) {
        sets.push(`${match.set3_player_one_games}-${match.set3_player_two_games}`);
    }

    return sets.join(', ');
}

function formatScoreForUser(match: LeagueMatch, userId: number): string {
    if (match.status !== 'played') {
        return '—';
    }

    const isPlayerOne = match.player_one.id === userId;
    const sets: string[] = [];

    if (match.set1_player_one_games !== null && match.set1_player_two_games !== null) {
        sets.push(
            isPlayerOne
                ? `${match.set1_player_one_games}-${match.set1_player_two_games}`
                : `${match.set1_player_two_games}-${match.set1_player_one_games}`,
        );
    }

    if (match.set2_player_one_games !== null && match.set2_player_two_games !== null) {
        sets.push(
            isPlayerOne
                ? `${match.set2_player_one_games}-${match.set2_player_two_games}`
                : `${match.set2_player_two_games}-${match.set2_player_one_games}`,
        );
    }

    if (match.set3_player_one_games !== null && match.set3_player_two_games !== null) {
        sets.push(
            isPlayerOne
                ? `${match.set3_player_one_games}-${match.set3_player_two_games}`
                : `${match.set3_player_two_games}-${match.set3_player_one_games}`,
        );
    }

    return sets.join(', ');
}

function matchInvolvesUser(match: LeagueMatch, userId: number): boolean {
    return match.player_one.id === userId || match.player_two.id === userId;
}

function MatchPlayerChip({
    player,
    highlighted = false,
}: {
    player: LeagueMatchPlayer;
    highlighted?: boolean;
}) {
    const getInitials = useInitials();

    return (
        <div className="flex items-center gap-2">
            <Avatar className="size-8 shrink-0">
                <AvatarImage src={player.avatar ?? undefined} alt={player.name} />
                <AvatarFallback className="bg-neutral-200 text-xs font-medium text-black dark:bg-neutral-700 dark:text-white">
                    {getInitials(player.name)}
                </AvatarFallback>
            </Avatar>
            <span className={cn('font-medium', highlighted && 'text-primary')}>{player.name}</span>
        </div>
    );
}

function MatchPlayersRow({
    playerOne,
    playerTwo,
    highlightUserId,
}: {
    playerOne: LeagueMatchPlayer;
    playerTwo: LeagueMatchPlayer;
    highlightUserId?: number | null;
}) {
    const { t } = useI18n();

    return (
        <div className="flex flex-wrap items-center gap-x-3 gap-y-2">
            <MatchPlayerChip
                player={playerOne}
                highlighted={highlightUserId === playerOne.id}
            />
            <span className="text-xs font-normal uppercase tracking-wide text-muted-foreground">
                {t('league_vs')}
            </span>
            <MatchPlayerChip
                player={playerTwo}
                highlighted={highlightUserId === playerTwo.id}
            />
        </div>
    );
}

export function LeagueMatchesList({
    matches,
    currentUserId,
    filterUserId,
    perspectiveUserId,
    onEnterResult,
}: LeagueMatchesListProps) {
    const { t } = useI18n();

    const isAdminMode = onEnterResult !== undefined;

    const visibleMatches =
        filterUserId !== undefined && filterUserId !== null
            ? matches.filter((match) => matchInvolvesUser(match, filterUserId))
            : matches;

    const perspectiveId = perspectiveUserId ?? currentUserId ?? null;

    if (visibleMatches.length === 0) {
        return (
            <p className="text-sm text-muted-foreground">{t('league_no_matches')}</p>
        );
    }

    return (
        <div className="space-y-3">
            {visibleMatches.map((match) => {
                const involvesPerspectiveUser =
                    perspectiveId !== null && matchInvolvesUser(match, perspectiveId);

                const scoreText =
                    !isAdminMode && involvesPerspectiveUser && perspectiveId
                        ? formatScoreForUser(match, perspectiveId)
                        : formatScore(match);

                const involvesCurrentUser =
                    currentUserId !== undefined &&
                    currentUserId !== null &&
                    matchInvolvesUser(match, currentUserId);

                return (
                    <Card
                        key={match.id}
                        className={involvesCurrentUser ? 'border-primary/40 bg-primary/5' : undefined}
                    >
                        <CardContent className="flex flex-col gap-3 p-4 sm:flex-row sm:items-center sm:justify-between">
                            <div className="space-y-2">
                                <MatchPlayersRow
                                    playerOne={match.player_one}
                                    playerTwo={match.player_two}
                                    highlightUserId={currentUserId}
                                />
                                <div className="flex flex-wrap items-center gap-2">
                                    <Badge variant="outline">
                                        {t('league_round')} {match.round}
                                    </Badge>
                                    <Badge variant={match.status === 'played' ? 'default' : 'secondary'}>
                                        {match.status === 'played' ? t('league_played') : t('league_pending')}
                                    </Badge>
                                </div>
                            </div>
                            <div className="flex shrink-0 items-center gap-3">
                                {scoreText !== '—' && (
                                    <span className="text-sm text-muted-foreground">{scoreText}</span>
                                )}
                                {onEnterResult && (
                                    <Button
                                        variant="outline"
                                        onClick={() => onEnterResult(match)}
                                    >
                                        {match.status === 'played'
                                            ? t('league_edit_result')
                                            : t('league_enter_result')}
                                    </Button>
                                )}
                                {!onEnterResult && (
                                    <div className="text-sm text-muted-foreground">{scoreText}</div>
                                )}
                            </div>
                        </CardContent>
                    </Card>
                );
            })}
        </div>
    );
}
