import type { EloRankingEntry } from '@/components/ranking/types';
import { useI18n } from '@/lib/i18n';

type EloRankingTableProps = {
    rankings: EloRankingEntry[];
    highlightUserId?: number | null;
};

function isEntryHighlighted(entry: EloRankingEntry, highlightUserId?: number | null): boolean {
    return highlightUserId !== undefined && highlightUserId !== null && entry.user_id === highlightUserId;
}

export function EloRankingTable({ rankings, highlightUserId }: EloRankingTableProps) {
    const { t } = useI18n();

    if (rankings.length === 0) {
        return <p className="text-sm text-muted-foreground">{t('ranking_empty')}</p>;
    }

    return (
        <>
            <ul className="divide-y overflow-hidden rounded-lg border md:hidden">
                {rankings.map((entry, index) => {
                    const highlighted = isEntryHighlighted(entry, highlightUserId);

                    return (
                        <li
                            key={entry.user_id}
                            className={`flex flex-col gap-2 px-3 py-3 ${highlighted ? 'bg-primary/5' : ''}`}
                        >
                            <div className="flex items-center justify-between gap-3">
                                <div className="flex min-w-0 items-center gap-3">
                                    <span className="w-6 shrink-0 text-sm text-muted-foreground">{index + 1}</span>
                                    <span className="truncate font-medium">{entry.name}</span>
                                </div>
                                <span className="shrink-0 font-semibold tabular-nums">{entry.elo}</span>
                            </div>
                            <div className="grid grid-cols-3 gap-2 pl-9 text-center text-xs">
                                <div className="rounded-md bg-muted/50 px-2 py-1.5">
                                    <p className="text-muted-foreground">{t('ranking_matches_played')}</p>
                                    <p className="font-medium tabular-nums">{entry.matches_played}</p>
                                </div>
                                <div className="rounded-md bg-muted/50 px-2 py-1.5">
                                    <p className="text-muted-foreground">{t('ranking_wins')}</p>
                                    <p className="font-medium tabular-nums">{entry.wins}</p>
                                </div>
                                <div className="rounded-md bg-muted/50 px-2 py-1.5">
                                    <p className="text-muted-foreground">{t('ranking_losses')}</p>
                                    <p className="font-medium tabular-nums">{entry.losses}</p>
                                </div>
                            </div>
                        </li>
                    );
                })}
            </ul>

            <div className="hidden overflow-x-auto rounded-lg border md:block">
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
                            const highlighted = isEntryHighlighted(entry, highlightUserId);

                            return (
                                <tr
                                    key={entry.user_id}
                                    className={highlighted ? 'bg-primary/5' : 'border-t'}
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
        </>
    );
}
