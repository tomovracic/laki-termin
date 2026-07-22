import type { EloRankingEntry } from '@/components/ranking/types';
import { useI18n } from '@/lib/i18n';

type EloRankingTableProps = {
    rankings: EloRankingEntry[];
    highlightUserId?: number | null;
};

export function EloRankingTable({ rankings, highlightUserId }: EloRankingTableProps) {
    const { t } = useI18n();

    if (rankings.length === 0) {
        return <p className="text-sm text-muted-foreground">{t('ranking_empty')}</p>;
    }

    return (
        <div className="overflow-x-auto rounded-lg border">
            <table className="w-full min-w-[640px] text-sm">
                <thead className="bg-muted/50">
                    <tr>
                        <th className="px-4 py-3 text-left font-medium">#</th>
                        <th className="px-4 py-3 text-left font-medium">{t('ranking_player')}</th>
                        <th className="px-4 py-3 text-center font-medium">{t('ranking_elo')}</th>
                        <th className="px-4 py-3 text-center font-medium">{t('ranking_matches_played')}</th>
                        <th className="px-4 py-3 text-center font-medium">{t('ranking_wins')}</th>
                        <th className="px-4 py-3 text-center font-medium">{t('ranking_losses')}</th>
                    </tr>
                </thead>
                <tbody>
                    {rankings.map((entry, index) => {
                        const isHighlighted =
                            highlightUserId !== undefined &&
                            highlightUserId !== null &&
                            entry.user_id === highlightUserId;

                        return (
                            <tr
                                key={entry.user_id}
                                className={isHighlighted ? 'bg-primary/5' : 'border-t'}
                            >
                                <td className="px-4 py-3 text-muted-foreground">{index + 1}</td>
                                <td className="px-4 py-3 font-medium">{entry.name}</td>
                                <td className="px-4 py-3 text-center font-semibold tabular-nums">{entry.elo}</td>
                                <td className="px-4 py-3 text-center">{entry.matches_played}</td>
                                <td className="px-4 py-3 text-center">{entry.wins}</td>
                                <td className="px-4 py-3 text-center">{entry.losses}</td>
                            </tr>
                        );
                    })}
                </tbody>
            </table>
        </div>
    );
}
