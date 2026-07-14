import { useMemo, useState } from 'react';
import { MatchHistoryList } from '@/components/match-history/match-history-list';
import { PlayerNameAutocomplete } from '@/components/match-history/player-name-autocomplete';
import type { MatchHistoryEntry, MatchHistoryPlayerInput } from '@/components/match-history/types';
import { Card, CardContent, CardHeader } from '@/components/ui/card';
import { ToggleGroup, ToggleGroupItem } from '@/components/ui/toggle-group';
import { useI18n } from '@/lib/i18n';

type MatchTab = 'mine' | 'all';

type MatchHistorySectionProps = {
    matches: MatchHistoryEntry[];
    currentUserId: number | null;
    deletingMatchId?: string | null;
    onEdit?: (match: MatchHistoryEntry) => void;
    onDelete?: (match: MatchHistoryEntry) => void;
};

function matchInvolvesUser(match: MatchHistoryEntry, userId: number): boolean {
    return match.player_one.user_id === userId || match.player_two.user_id === userId;
}

const emptyPlayerFilter: MatchHistoryPlayerInput = {
    user_id: null,
    first_name: '',
    last_name: '',
    display_name: '',
};

export function MatchHistorySection({
    matches,
    currentUserId,
    deletingMatchId = null,
    onEdit,
    onDelete,
}: MatchHistorySectionProps) {
    const { t } = useI18n();
    const [activeTab, setActiveTab] = useState<MatchTab>('mine');
    const [playerFilter, setPlayerFilter] = useState<MatchHistoryPlayerInput>(emptyPlayerFilter);

    const filteredMatches = useMemo(() => {
        if (activeTab === 'mine' && currentUserId !== null) {
            return matches.filter((match) => matchInvolvesUser(match, currentUserId));
        }

        if (playerFilter.user_id !== null) {
            return matches.filter((match) => matchInvolvesUser(match, playerFilter.user_id as number));
        }

        return matches;
    }, [activeTab, currentUserId, matches, playerFilter.user_id]);

    function handleTabChange(value: string) {
        if (value === 'mine' || value === 'all') {
            setActiveTab(value);

            if (value === 'mine') {
                setPlayerFilter(emptyPlayerFilter);
            }
        }
    }

    return (
        <Card>
            <CardHeader>
                <div className="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                    <ToggleGroup
                        type="single"
                        variant="outline"
                        value={activeTab}
                        onValueChange={handleTabChange}
                    >
                        <ToggleGroupItem value="mine">{t('league_my_matches')}</ToggleGroupItem>
                        <ToggleGroupItem value="all">{t('league_all_matches')}</ToggleGroupItem>
                    </ToggleGroup>

                    {activeTab === 'all' && (
                        <div className="w-full sm:w-72">
                            <PlayerNameAutocomplete
                                id="match-history-player-filter"
                                label={t('league_filter_by_player')}
                                value={playerFilter}
                                onChange={setPlayerFilter}
                                hideLabel
                                inputClassName="h-9"
                            />
                        </div>
                    )}
                </div>
            </CardHeader>
            <CardContent>
                <MatchHistoryList
                    matches={filteredMatches}
                    currentUserId={currentUserId}
                    deletingMatchId={deletingMatchId}
                    onEdit={onEdit}
                    onDelete={onDelete}
                />
            </CardContent>
        </Card>
    );
}
