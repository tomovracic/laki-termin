import type { BracketPreviewMatch, LeagueMatch, LeagueMatchPlayer } from '@/components/league/types';
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
import { CalendarDays, ChevronLeft, ChevronRight, Trophy } from 'lucide-react';
import { useState } from 'react';

type TournamentBracketProps = {
    matches?: LeagueMatch[];
    previewMatches?: BracketPreviewMatch[];
    onEnterResult?: (match: LeagueMatch) => void;
    onFinishRound?: () => void;
    canEnterResults?: boolean;
    canFinishRound?: boolean;
    isFinishingRound?: boolean;
    currentUserId?: number | null;
    currentBracketRound?: number | null;
    nextRoundPending?: boolean;
    championName?: string | null;
};

function playerLabel(name: string | null | undefined, byeLabel: string, tbaLabel: string): string {
    if (name === null || name === undefined || name.trim() === '') {
        return tbaLabel;
    }

    return name;
}

function toDisplayPlayer(
    player: LeagueMatchPlayer | null,
    fallbackName: string,
): MatchDisplayPlayer {
    return {
        userId: player?.id ?? null,
        name: player?.name ?? fallbackName,
        avatar: player?.avatar,
    };
}

export function TournamentBracket({
    matches,
    previewMatches,
    onEnterResult,
    onFinishRound,
    canEnterResults = false,
    canFinishRound = false,
    isFinishingRound = false,
    currentUserId = null,
    currentBracketRound = null,
    nextRoundPending = false,
    championName = null,
}: TournamentBracketProps) {
    const { t } = useI18n();

    if (previewMatches && previewMatches.length > 0) {
        return (
            <div className="space-y-3">
                <h3 className="text-sm font-medium">{t('tournament_bracket_preview')}</h3>
                <div className="grid gap-2 sm:grid-cols-2">
                    {previewMatches.map((match) => (
                        <div
                            key={`preview-${match.position}`}
                            className={cn(
                                'rounded-md border p-3 text-sm',
                                (match.is_bye || match.is_empty) && 'border-dashed bg-muted/30',
                            )}
                        >
                            <div className="mb-2 flex items-center justify-between gap-2">
                                <span className="text-xs text-muted-foreground">
                                    {t('league_round')} 1 · #{match.position + 1}
                                </span>
                                {match.is_empty ? (
                                    <Badge variant="outline">{t('tournament_empty_slot')}</Badge>
                                ) : match.is_bye ? (
                                    <Badge variant="outline">{t('tournament_bye')}</Badge>
                                ) : null}
                            </div>
                            {match.is_empty ? (
                                <p className="text-muted-foreground">{t('tournament_empty_slot')}</p>
                            ) : (
                                <div className="space-y-1">
                                    <p className="font-medium">
                                        {playerLabel(
                                            match.player_one,
                                            t('tournament_bye'),
                                            t('tournament_tba'),
                                        )}
                                    </p>
                                    <p className="text-xs text-muted-foreground">{t('league_vs')}</p>
                                    <p className="font-medium">
                                        {match.is_bye
                                            ? t('tournament_bye')
                                            : playerLabel(
                                                  match.player_two,
                                                  t('tournament_bye'),
                                                  t('tournament_tba'),
                                              )}
                                    </p>
                                </div>
                            )}
                        </div>
                    ))}
                </div>
            </div>
        );
    }

    if (!matches || matches.length === 0) {
        return <p className="text-sm text-muted-foreground">{t('tournament_no_bracket')}</p>;
    }

    const rounds = [...new Set(matches.map((match) => match.bracket_round ?? match.round))].sort(
        (a, b) => a - b,
    );

    return (
        <div className="space-y-3">
            {championName !== null && championName !== '' && (
                <div className="flex items-center gap-2 rounded-md border border-amber-500/40 bg-amber-500/10 px-3 py-2.5">
                    <Trophy className="size-4 shrink-0 text-amber-600 dark:text-amber-400" aria-hidden />
                    <p className="text-sm font-semibold">
                        {t('tournament_champion')}: {championName}
                    </p>
                </div>
            )}
            {canFinishRound && onFinishRound && (
                <div className="flex flex-col gap-2 rounded-md border border-primary/30 bg-primary/5 px-3 py-3 sm:flex-row sm:items-center sm:justify-between">
                    <p className="text-sm text-muted-foreground">{t('tournament_finish_round_hint')}</p>
                    <Button
                        type="button"
                        size="sm"
                        disabled={isFinishingRound}
                        onClick={onFinishRound}
                        className="shrink-0 self-start sm:self-auto"
                    >
                        {isFinishingRound ? t('saving') : t('tournament_finish_round')}
                    </Button>
                </div>
            )}
            {canFinishRound && !onFinishRound && (
                <p className="text-sm text-muted-foreground">{t('tournament_awaiting_finish_round')}</p>
            )}
            {!canFinishRound && nextRoundPending && (
                <p className="text-sm text-muted-foreground">{t('tournament_next_round_pending')}</p>
            )}
            <BracketCarousel
                rounds={rounds}
                matches={matches}
                onEnterResult={onEnterResult}
                canEnterResults={canEnterResults}
                currentUserId={currentUserId}
                currentBracketRound={currentBracketRound}
            />
        </div>
    );
}

type MatchCardProps = {
    match: LeagueMatch;
    canEnterResults: boolean;
    currentUserId: number | null;
    currentBracketRound: number | null;
    onEnterResult?: (match: LeagueMatch) => void;
};

function matchInvolvesUser(match: LeagueMatch, userId: number): boolean {
    return match.player_one?.id === userId || match.player_two?.id === userId;
}

function BracketMatchMetadata({
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
            {match.is_empty ? (
                <Badge variant="outline">{t('tournament_empty_slot')}</Badge>
            ) : match.is_bye ? (
                <Badge variant="outline">{t('tournament_bye')}</Badge>
            ) : (
                <Badge variant={match.status === 'played' ? 'default' : 'secondary'}>
                    {match.status === 'played' ? t('league_played') : t('league_pending')}
                </Badge>
            )}
            <Badge variant="outline">
                {t('league_round')} {match.bracket_round ?? match.round}
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

function MatchCard({
    match,
    canEnterResults,
    currentUserId,
    currentBracketRound,
    onEnterResult,
}: MatchCardProps) {
    const { t, locale } = useI18n();

    const matchRound = match.bracket_round ?? match.round;
    const isCurrentRound =
        currentBracketRound === null || currentBracketRound === matchRound;

    const canEdit =
        canEnterResults &&
        !match.is_bye &&
        !match.is_empty &&
        match.player_one !== null &&
        match.player_two !== null &&
        isCurrentRound;

    const involvesCurrentUser =
        currentUserId !== null && matchInvolvesUser(match, currentUserId);

    const setScores = match.is_bye || match.is_empty ? [] : getSetScores(match);
    const tbaLabel = t('tournament_tba');
    const byeLabel = t('tournament_bye');
    const emptyLabel = t('tournament_empty_slot');

    return (
        <Card
            className={cn(
                'gap-0 py-0',
                involvesCurrentUser && 'border-primary/40 bg-primary/5',
                (match.is_bye || match.is_empty) && 'border-dashed',
            )}
        >
            <CardContent className="space-y-2 p-3">
                <div className="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                    {match.is_empty ? (
                        <p className="text-sm text-muted-foreground">{emptyLabel}</p>
                    ) : (
                        <MatchScoreboard
                            playerOne={toDisplayPlayer(
                                match.player_one,
                                match.is_bye ? byeLabel : tbaLabel,
                            )}
                            playerTwo={toDisplayPlayer(
                                match.is_bye ? null : match.player_two,
                                match.is_bye ? byeLabel : tbaLabel,
                            )}
                            sets={setScores}
                            highlightUserId={currentUserId}
                        />
                    )}
                    {canEdit && onEnterResult && (
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
                <BracketMatchMetadata match={match} locale={locale} t={t} />
            </CardContent>
        </Card>
    );
}

type BracketCarouselProps = {
    rounds: number[];
    matches: LeagueMatch[];
    canEnterResults: boolean;
    currentUserId: number | null;
    currentBracketRound: number | null;
    onEnterResult?: (match: LeagueMatch) => void;
};

function BracketCarousel({
    rounds,
    matches,
    canEnterResults,
    currentUserId,
    currentBracketRound,
    onEnterResult,
}: BracketCarouselProps) {
    const { t } = useI18n();
    const [activeIndex, setActiveIndex] = useState(() => Math.max(0, rounds.length - 1));

    const currentRound = rounds[activeIndex];
    const roundMatches = matches
        .filter((match) => (match.bracket_round ?? match.round) === currentRound)
        .sort((a, b) => (a.bracket_position ?? 0) - (b.bracket_position ?? 0) || a.id - b.id);

    const roundLabel = (() => {
        const roundMatches = matches.filter(
            (match) => (match.bracket_round ?? match.round) === currentRound,
        );
        const competitive = roundMatches.filter((match) => !match.is_bye && !match.is_empty);
        const playerIds = new Set<number>();

        for (const match of competitive) {
            if (match.player_one?.id != null) {
                playerIds.add(match.player_one.id);
            }

            if (match.player_two?.id != null) {
                playerIds.add(match.player_two.id);
            }
        }

        if (competitive.length === 3 && playerIds.size === 3) {
            return t('tournament_final_three');
        }

        if (competitive.length === 1 && playerIds.size === 2 && activeIndex === rounds.length - 1) {
            return t('tournament_final');
        }

        return `${t('league_round')} ${currentRound}`;
    })();

    return (
        <div className="space-y-4">
            <div className="flex items-center justify-between gap-2">
                <Button
                    type="button"
                    variant="outline"
                    size="icon"
                    onClick={() => setActiveIndex((i) => Math.max(0, i - 1))}
                    disabled={activeIndex === 0}
                    aria-label={t('tournament_prev_round')}
                >
                    <ChevronLeft className="size-4" />
                </Button>

                <div className="flex flex-col items-center gap-1">
                    <span className="text-sm font-semibold">{roundLabel}</span>
                    <div className="flex gap-1">
                        {rounds.map((_, i) => (
                            <button
                                key={i}
                                type="button"
                                aria-label={`${t('league_round')} ${i + 1}`}
                                onClick={() => setActiveIndex(i)}
                                className={cn(
                                    'size-2 rounded-full transition-colors',
                                    i === activeIndex ? 'bg-primary' : 'bg-muted-foreground/30',
                                )}
                            />
                        ))}
                    </div>
                </div>

                <Button
                    type="button"
                    variant="outline"
                    size="icon"
                    onClick={() => setActiveIndex((i) => Math.min(rounds.length - 1, i + 1))}
                    disabled={activeIndex === rounds.length - 1}
                    aria-label={t('tournament_next_round')}
                >
                    <ChevronRight className="size-4" />
                </Button>
            </div>

            <div className="space-y-3">
                {roundMatches.map((match) => (
                    <MatchCard
                        key={match.id}
                        match={match}
                        canEnterResults={canEnterResults}
                        currentUserId={currentUserId}
                        currentBracketRound={currentBracketRound}
                        onEnterResult={onEnterResult}
                    />
                ))}
            </div>
        </div>
    );
}
