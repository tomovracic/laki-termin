import type { LeagueStandingsEntry } from '@/components/league/types';
import { useI18n } from '@/lib/i18n';

type LeagueStandingsTableProps = {
    standings: LeagueStandingsEntry[];
    highlightUserId?: number | null;
    qualifyingParticipantIds?: Set<number>;
    showGameDifference?: boolean;
};

function formatDifference(value: number): string {
    return value > 0 ? `+${value}` : `${value}`;
}

function rowToneClass(isHighlighted: boolean, qualifies: boolean): string {
    if (isHighlighted) {
        return 'bg-primary/5';
    }

    if (qualifies) {
        return 'bg-emerald-500/5';
    }

    return '';
}

export function LeagueStandingsTable({
    standings,
    highlightUserId,
    qualifyingParticipantIds,
    showGameDifference = false,
}: LeagueStandingsTableProps) {
    const { t } = useI18n();

    if (standings.length === 0) {
        return (
            <p className="text-sm text-muted-foreground">
                {t('league_no_standings')}
            </p>
        );
    }

    return (
        <>
            <ul className="divide-y overflow-hidden rounded-lg border md:hidden">
                {standings.map((entry, index) => {
                    const isHighlighted =
                        highlightUserId !== undefined &&
                        highlightUserId !== null &&
                        entry.user_id === highlightUserId;
                    const qualifies =
                        qualifyingParticipantIds?.has(entry.participant_id) ??
                        false;

                    return (
                        <li
                            key={entry.participant_id}
                            className={`flex flex-col gap-2 px-3 py-3 ${rowToneClass(isHighlighted, qualifies)}`}
                        >
                            <div className="flex min-w-0 items-center gap-3">
                                <span className="w-6 shrink-0 text-sm text-muted-foreground">
                                    {index + 1}
                                </span>
                                <span className="truncate font-medium">
                                    {entry.name}
                                </span>
                            </div>
                            <div
                                className={`grid gap-2 pl-9 text-center text-xs ${showGameDifference ? 'grid-cols-3' : 'grid-cols-2'}`}
                            >
                                <div className="rounded-md bg-muted/50 px-2 py-1.5">
                                    <p className="text-muted-foreground">
                                        {t('league_matches_played')}
                                    </p>
                                    <p className="font-medium tabular-nums">
                                        {entry.matches_played}
                                    </p>
                                </div>
                                <div className="rounded-md bg-muted/50 px-2 py-1.5">
                                    <p className="text-muted-foreground">
                                        {t('league_wins')}
                                    </p>
                                    <p className="font-medium tabular-nums">
                                        {entry.wins}
                                    </p>
                                </div>
                                <div className="rounded-md bg-muted/50 px-2 py-1.5">
                                    <p className="text-muted-foreground">
                                        {t('league_losses')}
                                    </p>
                                    <p className="font-medium tabular-nums">
                                        {entry.losses}
                                    </p>
                                </div>
                                <div className="rounded-md bg-muted/50 px-2 py-1.5">
                                    <p className="text-muted-foreground">
                                        {t('league_set_difference_short')}
                                    </p>
                                    <p className="font-medium tabular-nums">
                                        {formatDifference(entry.set_difference)}
                                    </p>
                                </div>
                                {showGameDifference && (
                                    <div className="rounded-md bg-muted/50 px-2 py-1.5">
                                        <p className="text-muted-foreground">
                                            {t('league_game_difference_short')}
                                        </p>
                                        <p className="font-medium tabular-nums">
                                            {formatDifference(
                                                entry.game_difference ?? 0,
                                            )}
                                        </p>
                                    </div>
                                )}
                            </div>
                        </li>
                    );
                })}
            </ul>

            <div className="hidden overflow-x-auto rounded-lg border md:block">
                <table className="w-full min-w-[640px] text-sm">
                    <thead className="bg-muted/50">
                        <tr>
                            <th className="px-4 py-3 text-left font-medium">
                                #
                            </th>
                            <th className="px-4 py-3 text-left font-medium">
                                {t('league_player')}
                            </th>
                            <th className="px-4 py-3 text-center font-medium">
                                {t('league_matches_played')}
                            </th>
                            <th className="px-4 py-3 text-center font-medium">
                                {t('league_wins')}
                            </th>
                            <th className="px-4 py-3 text-center font-medium">
                                {t('league_losses')}
                            </th>
                            <th className="px-4 py-3 text-center font-medium">
                                {t('league_set_difference')}
                            </th>
                            {showGameDifference && (
                                <th className="px-4 py-3 text-center font-medium">
                                    {t('league_game_difference')}
                                </th>
                            )}
                        </tr>
                    </thead>
                    <tbody>
                        {standings.map((entry, index) => {
                            const isHighlighted =
                                highlightUserId !== undefined &&
                                highlightUserId !== null &&
                                entry.user_id === highlightUserId;
                            const qualifies =
                                qualifyingParticipantIds?.has(
                                    entry.participant_id,
                                ) ?? false;

                            return (
                                <tr
                                    key={entry.participant_id}
                                    className={
                                        isHighlighted || qualifies
                                            ? rowToneClass(
                                                  isHighlighted,
                                                  qualifies,
                                              )
                                            : 'border-t'
                                    }
                                >
                                    <td className="px-4 py-3 text-muted-foreground">
                                        {index + 1}
                                    </td>
                                    <td className="px-4 py-3 font-medium">
                                        {entry.name}
                                    </td>
                                    <td className="px-4 py-3 text-center">
                                        {entry.matches_played}
                                    </td>
                                    <td className="px-4 py-3 text-center">
                                        {entry.wins}
                                    </td>
                                    <td className="px-4 py-3 text-center">
                                        {entry.losses}
                                    </td>
                                    <td className="px-4 py-3 text-center">
                                        {formatDifference(entry.set_difference)}
                                    </td>
                                    {showGameDifference && (
                                        <td className="px-4 py-3 text-center">
                                            {formatDifference(
                                                entry.game_difference ?? 0,
                                            )}
                                        </td>
                                    )}
                                </tr>
                            );
                        })}
                    </tbody>
                </table>
            </div>
        </>
    );
}
