import type { FormEvent } from 'react';
import { useEffect, useState } from 'react';
import {
    buildScorePayload,
    MatchScoreInputs,
    type MatchScoreValues,
} from '@/components/match-history/match-score-inputs';
import type { MatchHistoryEntry, UpdatePlayedMatchPayload } from '@/components/match-history/types';
import { Button } from '@/components/ui/button';
import { useI18n } from '@/lib/i18n';
import { cn } from '@/lib/utils';

type EditPlayedMatchFormProps = {
    match: MatchHistoryEntry;
    onSubmit: (payload: UpdatePlayedMatchPayload) => Promise<void>;
    onCancel: () => void;
    isSubmitting: boolean;
    errors?: Record<string, string[]>;
    resultErrors?: string[];
};

const playerRowClassName = 'flex h-9 items-center truncate font-medium md:h-10';

function scoreValue(value: number | null): string {
    return value === null ? '' : String(value);
}

function buildInitialScoreValues(match: MatchHistoryEntry): MatchScoreValues {
    return {
        set1_player_one_games: scoreValue(match.set1_player_one_games),
        set1_player_two_games: scoreValue(match.set1_player_two_games),
        set2_player_one_games: scoreValue(match.set2_player_one_games),
        set2_player_two_games: scoreValue(match.set2_player_two_games),
        set3_player_one_games: scoreValue(match.set3_player_one_games),
        set3_player_two_games: scoreValue(match.set3_player_two_games),
    };
}

export function EditPlayedMatchForm({
    match,
    onSubmit,
    onCancel,
    isSubmitting,
    errors = {},
    resultErrors = [],
}: EditPlayedMatchFormProps) {
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
            <div className="flex min-w-0 flex-col gap-1.5">
                <p className={cn(playerRowClassName)}>{match.player_one.name}</p>
                <p className={cn(playerRowClassName)}>{match.player_two.name}</p>
            </div>

            <MatchScoreInputs
                values={scoreValues}
                onChange={setScoreValues}
                errors={resultErrors.length > 0 ? resultErrors : errors.result}
            />

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
