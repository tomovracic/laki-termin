import { useMemo, useState } from 'react';
import { LeagueMatchesSection } from '@/components/league/league-matches-section';
import { LeagueStandingsTable } from '@/components/league/league-standings-table';
import type {
    LeagueGroupSummary,
    LeagueMatch,
    LeagueStandingsEntry,
} from '@/components/league/types';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Separator } from '@/components/ui/separator';
import { useI18n } from '@/lib/i18n';

type GroupStageSectionProps = {
    groups: LeagueGroupSummary[];
    qualifiers: LeagueStandingsEntry[];
    highlightUserId?: number | null;
    matches?: LeagueMatch[];
    currentUserId?: number | null;
    showMatches?: boolean;
    heading?: string;
    onEnterResult?: (match: LeagueMatch) => void;
};

function pickDefaultGroupId(
    groups: LeagueGroupSummary[],
    userId: number | null,
): number | null {
    const userGroup = groups.find((group) =>
        group.standings.some((entry) => entry.user_id === userId),
    );

    return userGroup?.id ?? groups[0]?.id ?? null;
}

export function GroupStageSection({
    groups,
    qualifiers,
    highlightUserId = null,
    matches = [],
    currentUserId = null,
    showMatches = false,
    heading,
    onEnterResult,
}: GroupStageSectionProps) {
    const { t } = useI18n();
    const [selectedGroupId, setSelectedGroupId] = useState<number | null>(() =>
        pickDefaultGroupId(groups, currentUserId),
    );
    const qualifierIds = new Set(
        qualifiers.map((entry) => entry.participant_id),
    );
    const selectedGroup =
        groups.find((group) => group.id === selectedGroupId) ??
        groups.find((group) =>
            group.standings.some((entry) => entry.user_id === currentUserId),
        ) ??
        groups[0] ??
        null;
    const selectedMatches = useMemo(() => {
        if (selectedGroup === null) {
            return [];
        }

        return matches.filter(
            (match) => match.league_group_id === selectedGroup.id,
        );
    }, [matches, selectedGroup]);

    return (
        <div className="space-y-4">
            {selectedGroup !== null && (
                <Card>
                    <CardHeader className="space-y-3">
                        {heading !== undefined && heading !== '' && (
                            <CardTitle>{heading}</CardTitle>
                        )}
                        <div
                            className="flex gap-2 overflow-x-auto pb-1"
                            role="tablist"
                            aria-label={t('tournament_step_groups')}
                        >
                            {groups.map((group) => {
                                const isActive = group.id === selectedGroup.id;

                                return (
                                    <Button
                                        key={group.id}
                                        id={`group-tab-${group.id}`}
                                        type="button"
                                        role="tab"
                                        aria-selected={isActive}
                                        aria-controls={`group-panel-${group.id}`}
                                        variant={
                                            isActive ? 'default' : 'outline'
                                        }
                                        size="sm"
                                        className="shrink-0"
                                        onClick={() =>
                                            setSelectedGroupId(group.id)
                                        }
                                    >
                                        {t('tournament_group')} {group.name}
                                    </Button>
                                );
                            })}
                        </div>
                    </CardHeader>
                    <CardContent
                        id={`group-panel-${selectedGroup.id}`}
                        role="tabpanel"
                        aria-labelledby={`group-tab-${selectedGroup.id}`}
                        className="space-y-6"
                    >
                        <LeagueStandingsTable
                            standings={selectedGroup.standings}
                            highlightUserId={highlightUserId}
                            qualifyingParticipantIds={qualifierIds}
                            showGameDifference
                        />

                        {showMatches && (
                            <>
                                <Separator />
                                <LeagueMatchesSection
                                    key={selectedGroup.id}
                                    matches={selectedMatches}
                                    standings={selectedGroup.standings}
                                    currentUserId={currentUserId}
                                    onEnterResult={onEnterResult}
                                    embedded
                                />
                            </>
                        )}
                    </CardContent>
                </Card>
            )}
        </div>
    );
}
