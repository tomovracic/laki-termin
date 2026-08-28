import { knockoutRoundNameKey } from '@/components/league/bracket-utils';
import type {
    LeagueGroupSummary,
    LeagueMatch,
    LeagueMatchPlayer,
} from '@/components/league/types';
import {
    getSetScores,
    MatchScoreboard,
    type MatchDisplayPlayer,
} from '@/components/match/match-scoreboard';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { useI18n } from '@/lib/i18n';
import { cn } from '@/lib/utils';

type LeagueScheduleDialogProps = {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    matches: LeagueMatch[];
    groups?: LeagueGroupSummary[];
    currentUserId?: number | null;
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
    return (
        match.player_one?.id === userId ||
        match.player_two?.id === userId ||
        match.player_one?.partner_id === userId ||
        match.player_two?.partner_id === userId
    );
}

export function LeagueScheduleDialog({
    open,
    onOpenChange,
    matches,
    groups = [],
    currentUserId = null,
    onEnterResult,
}: LeagueScheduleDialogProps) {
    const { t } = useI18n();

    const scheduledMatches = matches
        .filter(
            (match) =>
                match.schedule_order != null &&
                !match.is_bye &&
                !match.is_empty,
        )
        .sort(
            (left, right) =>
                (left.schedule_order ?? 0) - (right.schedule_order ?? 0),
        );

    const nextPendingId =
        scheduledMatches.find((match) => match.status === 'pending')?.id ??
        null;

    function matchMetaLabel(match: LeagueMatch): string | null {
        if (match.bracket_round != null) {
            const roundMatches = matches.filter(
                (item) =>
                    (item.bracket_round ?? item.round) ===
                    (match.bracket_round ?? match.round),
            );
            const nameKey = knockoutRoundNameKey(roundMatches);

            return nameKey !== null
                ? t(nameKey)
                : `${t('league_round')} ${match.bracket_round}`;
        }

        if (match.league_group_id != null) {
            const group = groups.find(
                (item) => item.id === match.league_group_id,
            );

            return group !== undefined
                ? `${t('tournament_group')} ${group.name}`
                : null;
        }

        return `${t('league_round')} ${match.round}`;
    }

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="max-h-[90vh] overflow-y-auto sm:max-w-2xl">
                <DialogHeader>
                    <DialogTitle>{t('league_schedule')}</DialogTitle>
                    <DialogDescription>
                        {t('league_schedule_description')}
                    </DialogDescription>
                </DialogHeader>

                {scheduledMatches.length === 0 ? (
                    <p className="text-sm text-muted-foreground">
                        {t('league_schedule_empty')}
                    </p>
                ) : (
                    <ol className="space-y-3">
                        {scheduledMatches.map((match, index) => {
                            const involvesCurrentUser =
                                currentUserId !== null &&
                                matchInvolvesUser(match, currentUserId);
                            const isNext = match.id === nextPendingId;
                            const metaLabel = matchMetaLabel(match);

                            return (
                                <li key={match.id}>
                                    <Card
                                        className={cn(
                                            'gap-0 py-0',
                                            match.status === 'played' &&
                                                'opacity-80',
                                            involvesCurrentUser &&
                                                'border-primary/40 bg-primary/5',
                                            isNext &&
                                                'border-amber-500/50 bg-amber-500/5',
                                        )}
                                    >
                                        <CardContent className="space-y-2 p-3">
                                            <div className="flex items-start gap-3">
                                                <span className="mt-0.5 w-7 shrink-0 text-sm font-semibold tabular-nums text-muted-foreground">
                                                    {index + 1}.
                                                </span>
                                                <div className="min-w-0 flex-1 space-y-2">
                                                    <div className="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                                                        <MatchScoreboard
                                                            playerOne={toDisplayPlayer(
                                                                match.player_one,
                                                            )}
                                                            playerTwo={toDisplayPlayer(
                                                                match.player_two,
                                                            )}
                                                            sets={getSetScores(
                                                                match,
                                                            )}
                                                            highlightUserId={
                                                                currentUserId
                                                            }
                                                        />
                                                        {onEnterResult && (
                                                            <Button
                                                                type="button"
                                                                variant="outline"
                                                                size="sm"
                                                                className="shrink-0 self-end sm:self-auto"
                                                                onClick={() =>
                                                                    onEnterResult(
                                                                        match,
                                                                    )
                                                                }
                                                            >
                                                                {match.status ===
                                                                'played'
                                                                    ? t(
                                                                          'league_edit_result',
                                                                      )
                                                                    : t(
                                                                          'league_enter_result',
                                                                      )}
                                                            </Button>
                                                        )}
                                                    </div>
                                                    <div className="flex flex-wrap items-center gap-2">
                                                        <Badge
                                                            variant={
                                                                match.status ===
                                                                'played'
                                                                    ? 'default'
                                                                    : 'secondary'
                                                            }
                                                        >
                                                            {match.status ===
                                                            'played'
                                                                ? t(
                                                                      'league_played',
                                                                  )
                                                                : t(
                                                                      'league_pending',
                                                                  )}
                                                        </Badge>
                                                        {isNext && (
                                                            <Badge className="bg-amber-500 text-amber-950 hover:bg-amber-500/90">
                                                                {t(
                                                                    'league_schedule_next',
                                                                )}
                                                            </Badge>
                                                        )}
                                                        {metaLabel !== null && (
                                                            <Badge variant="outline">
                                                                {metaLabel}
                                                            </Badge>
                                                        )}
                                                    </div>
                                                </div>
                                            </div>
                                        </CardContent>
                                    </Card>
                                </li>
                            );
                        })}
                    </ol>
                )}
            </DialogContent>
        </Dialog>
    );
}
