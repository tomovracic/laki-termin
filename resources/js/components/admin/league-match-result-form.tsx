import type { FormEvent } from 'react';
import { useEffect, useState } from 'react';
import {
    buildScorePayload,
    MatchScoreInputs,
    type MatchScoreValues,
} from '@/components/match-history/match-score-inputs';
import type { LeagueMatch, LeagueMatchResultPayload } from '@/components/league/types';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { useI18n } from '@/lib/i18n';

type LeagueMatchResultFormProps = {
    match: LeagueMatch;
    onSubmit: (payload: LeagueMatchResultPayload) => Promise<void>;
    onCancel: () => void;
    isSubmitting: boolean;
    errors?: string[];
};

const playerOneNameClassName =
    'h-11 w-full min-w-0 pointer-events-none text-base font-medium bg-muted/40 text-right';
const playerTwoNameClassName =
    'h-11 w-full min-w-0 pointer-events-none text-base font-medium bg-muted/40 text-left';

function scoreValue(value: number | null): string {
    return value === null ? '' : String(value);
}

function buildInitialScoreValues(match: LeagueMatch): MatchScoreValues {
    return {
        set1_player_one_games: scoreValue(match.set1_player_one_games),
        set1_player_two_games: scoreValue(match.set1_player_two_games),
        set2_player_one_games: scoreValue(match.set2_player_one_games),
        set2_player_two_games: scoreValue(match.set2_player_two_games),
        set3_player_one_games: scoreValue(match.set3_player_one_games),
        set3_player_two_games: scoreValue(match.set3_player_two_games),
    };
}

export function LeagueMatchResultForm({
    match,
    onSubmit,
    onCancel,
    isSubmitting,
    errors = [],
}: LeagueMatchResultFormProps) {
    const { t } = useI18n();
    const [scoreValues, setScoreValues] = useState<MatchScoreValues>(() => buildInitialScoreValues(match));

    useEffect(() => {
        setScoreValues(buildInitialScoreValues(match));
    }, [match]);

    async function handleSubmit(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();
        await onSubmit(buildScorePayload(scoreValues));
    }

    return (
        <form onSubmit={handleSubmit} className="space-y-6">
            <div className="space-y-2">
                <div className="grid w-full grid-cols-[1fr_auto_1fr] items-center gap-x-2 gap-y-4">
                    <Input
                        value={match.player_one.name}
                        readOnly
                        tabIndex={-1}
                        className={playerOneNameClassName}
                        aria-label={match.player_one.name}
                    />

                    <span className="shrink-0 text-sm font-semibold uppercase tracking-wide text-muted-foreground">
                        {t('league_vs')}
                    </span>

                    <Input
                        value={match.player_two.name}
                        readOnly
                        tabIndex={-1}
                        className={playerTwoNameClassName}
                        aria-label={match.player_two.name}
                    />
                </div>

                <Badge variant="outline" className="w-fit font-normal text-muted-foreground">
                    {t('league_round')} {match.round}
                </Badge>
            </div>

            <MatchScoreInputs values={scoreValues} onChange={setScoreValues} errors={errors} />

            <div className="flex justify-end gap-2">
                <Button type="button" variant="outline" onClick={onCancel} disabled={isSubmitting}>
                    {t('cancel')}
                </Button>
                <Button type="submit" disabled={isSubmitting}>
                    {isSubmitting ? t('saving') : t('save')}
                </Button>
            </div>
        </form>
    );
}
