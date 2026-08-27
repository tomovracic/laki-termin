import type { LeagueStandingsEntry } from '@/components/league/types';
import { useI18n } from '@/lib/i18n';

type LeagueStandingsTableProps = {
    standings: LeagueStandingsEntry[];
    highlightUserId?: number | null;
    qualifyingParticipantIds?: Set<number>;
    showGameDifference?: boolean;
};

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
        <div className="overflow-x-auto rounded-lg border">
            <table className="w-full min-w-[640px] text-sm">
                <thead className="bg-muted/50">
                    <tr>
                        <th className="px-4 py-3 text-left font-medium">#</th>
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
                                    isHighlighted
                                        ? 'bg-primary/5'
                                        : qualifies
                                          ? 'border-t bg-emerald-500/5'
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
                                    {entry.set_difference > 0
                                        ? `+${entry.set_difference}`
                                        : entry.set_difference}
                                </td>
                                {showGameDifference && (
                                    <td className="px-4 py-3 text-center">
                                        {(entry.game_difference ?? 0) > 0
                                            ? `+${entry.game_difference}`
                                            : (entry.game_difference ?? 0)}
                                    </td>
                                )}
                            </tr>
                        );
                    })}
                </tbody>
            </table>
        </div>
    );
}
