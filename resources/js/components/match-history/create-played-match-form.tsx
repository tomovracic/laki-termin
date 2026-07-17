import type { FormEvent } from 'react';
import { useState } from 'react';
import {
    buildScorePayload,
    emptyMatchScoreValues,
    MatchScoreInputs,
    type MatchScoreValues,
} from '@/components/match-history/match-score-inputs';
import { PlayerNameAutocomplete } from '@/components/match-history/player-name-autocomplete';
import type { CreatePlayedMatchPayload, MatchHistoryPlayerInput } from '@/components/match-history/types';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { useI18n } from '@/lib/i18n';

type CreatePlayedMatchFormProps = {
    currentUserName: string;
    onSubmit: (payload: CreatePlayedMatchPayload) => Promise<void>;
    onCancel: () => void;
    isSubmitting: boolean;
    errors?: Record<string, string[]>;
    resultErrors?: string[];
};

const playerOneInputClassName =
    'h-11 w-full min-w-0 text-base font-medium bg-muted/40 text-right';
const opponentInputClassName =
    'h-11 w-full min-w-0 text-base font-medium bg-muted/40 text-left';

export function CreatePlayedMatchForm({
    currentUserName,
    onSubmit,
    onCancel,
    isSubmitting,
    errors = {},
    resultErrors = [],
}: CreatePlayedMatchFormProps) {
    const { t } = useI18n();
    const [playerTwo, setPlayerTwo] = useState<MatchHistoryPlayerInput>({
        user_id: null,
        first_name: '',
        last_name: '',
        display_name: '',
    });
    const [scoreValues, setScoreValues] = useState<MatchScoreValues>(emptyMatchScoreValues);

    const opponentError =
        errors['player_two']?.[0] ??
        errors['player_two.first_name']?.[0] ??
        errors['player_two.last_name']?.[0];

    async function handleSubmit(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();

        const scorePayload = buildScorePayload(scoreValues);

        const payload: CreatePlayedMatchPayload = {
            player_two:
                playerTwo.user_id !== null
                    ? { user_id: playerTwo.user_id }
                    : {
                          first_name: playerTwo.first_name,
                          last_name: playerTwo.last_name,
                      },
            set1_player_one_games: scorePayload.set1_player_one_games,
            set1_player_two_games: scorePayload.set1_player_two_games,
            set2_player_one_games: scorePayload.set2_player_one_games ?? 0,
            set2_player_two_games: scorePayload.set2_player_two_games ?? 0,
            set3_player_one_games: scorePayload.set3_player_one_games,
            set3_player_two_games: scorePayload.set3_player_two_games,
        };

        await onSubmit(payload);
    }

    return (
        <form onSubmit={handleSubmit} className="space-y-6">
            <div className="grid w-full grid-cols-[1fr_auto_1fr] items-center gap-x-2 gap-y-4">
                <Input
                    value={currentUserName}
                    readOnly
                    tabIndex={-1}
                    className={playerOneInputClassName}
                    aria-label={currentUserName}
                />

                <span className="shrink-0 text-sm font-semibold uppercase tracking-wide text-muted-foreground">
                    {t('league_vs')}
                </span>

                <div className="min-w-0 w-full">
                    <PlayerNameAutocomplete
                        id="player-two"
                        value={playerTwo}
                        onChange={setPlayerTwo}
                        hideLabel
                        error={opponentError}
                        inputClassName={opponentInputClassName}
                    />
                </div>
            </div>

            <MatchScoreInputs
                values={scoreValues}
                onChange={setScoreValues}
                errors={resultErrors}
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
