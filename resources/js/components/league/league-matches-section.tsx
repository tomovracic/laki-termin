import { useMemo, useState } from 'react';
import { LeagueMatchesList } from '@/components/league/league-matches-list';
import type { LeagueMatch, LeagueStandingsEntry } from '@/components/league/types';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader } from '@/components/ui/card';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { ToggleGroup, ToggleGroupItem } from '@/components/ui/toggle-group';
import { useI18n } from '@/lib/i18n';

type MatchTab = 'mine' | 'all';
type MatchStatus = 'played' | 'pending';

type LeagueMatchesSectionProps = {
    matches: LeagueMatch[];
    standings: LeagueStandingsEntry[];
    currentUserId: number | null;
    isParticipant: boolean;
    onEnterResult?: (match: LeagueMatch) => void;
};

export function LeagueMatchesSection({
    matches,
    standings,
    currentUserId,
    isParticipant,
    onEnterResult,
}: LeagueMatchesSectionProps) {
    const { t } = useI18n();
    const [activeTab, setActiveTab] = useState<MatchTab>(isParticipant ? 'mine' : 'all');
    const [filterUserId, setFilterUserId] = useState<string>('all');
    const [statusFilter, setStatusFilter] = useState<MatchStatus[]>([]);

    const selectedFilterUserId =
        filterUserId !== 'all' ? Number.parseInt(filterUserId, 10) : null;

    const statusFilteredMatches = useMemo(() => {
        if (statusFilter.length === 0) {
            return matches;
        }

        return matches.filter((match) => statusFilter.includes(match.status));
    }, [matches, statusFilter]);

    function handleStatusFilterChange(value: string[]) {
        setStatusFilter(value.filter((item): item is MatchStatus => item === 'played' || item === 'pending'));
    }

    return (
        <Card>
            <CardHeader>
                <div className="flex flex-col gap-4">
                    <div className="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                        <div className="flex items-center gap-2" role="tablist">
                            <Button
                                type="button"
                                role="tab"
                                aria-selected={activeTab === 'all'}
                                variant={activeTab === 'all' ? 'default' : 'outline'}
                                onClick={() => setActiveTab('all')}
                            >
                                {t('league_all_matches')}
                            </Button>
                            {isParticipant && (
                                <Button
                                    type="button"
                                    role="tab"
                                    aria-selected={activeTab === 'mine'}
                                    variant={activeTab === 'mine' ? 'default' : 'outline'}
                                    onClick={() => setActiveTab('mine')}
                                >
                                    {t('league_my_matches')}
                                </Button>
                            )}
                        </div>

                        {activeTab === 'all' && (
                            <Select value={filterUserId} onValueChange={setFilterUserId}>
                                <SelectTrigger className="w-full sm:w-56">
                                    <SelectValue placeholder={t('league_filter_by_player')} />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="all">{t('league_all_players')}</SelectItem>
                                    {standings.map((entry) => (
                                        <SelectItem key={entry.user_id} value={`${entry.user_id}`}>
                                            {entry.name}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                        )}
                    </div>

                    <ToggleGroup
                        type="multiple"
                        variant="outline"
                        value={statusFilter}
                        onValueChange={handleStatusFilterChange}
                    >
                        <ToggleGroupItem value="played">{t('league_played')}</ToggleGroupItem>
                        <ToggleGroupItem value="pending">{t('league_pending')}</ToggleGroupItem>
                    </ToggleGroup>
                </div>
            </CardHeader>
            <CardContent>
                {activeTab === 'mine' ? (
                    <LeagueMatchesList
                        matches={statusFilteredMatches}
                        currentUserId={currentUserId}
                        filterUserId={currentUserId}
                        onEnterResult={onEnterResult}
                    />
                ) : (
                    <LeagueMatchesList
                        matches={statusFilteredMatches}
                        currentUserId={currentUserId}
                        filterUserId={selectedFilterUserId}
                        onEnterResult={onEnterResult}
                    />
                )}
            </CardContent>
        </Card>
    );
}
