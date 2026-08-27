import { useMemo, useState } from 'react';
import { LeagueMatchesSection } from '@/components/league/league-matches-section';
import { LeagueStandingsTable } from '@/components/league/league-standings-table';
import type {
    LeagueGroupSummary,
    LeagueMatch,
    LeagueStandingsEntry,
} from '@/components/league/types';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Separator } from '@/components/ui/separator';
import { useI18n } from '@/lib/i18n';

type GroupStageSectionProps = {
    groups: LeagueGroupSummary[];
    qualifiers: LeagueStandingsEntry[];
    highlightUserId?: number | null;
    qualifyPerGroup?: number | null;
    bestRunnersUp?: number | null;
    matches?: LeagueMatch[];
    currentUserId?: number | null;
    showMatches?: boolean;
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
    qualifyPerGroup = 1,
    bestRunnersUp = 0,
    matches = [],
    currentUserId = null,
    showMatches = false,
    onEnterResult,
}: GroupStageSectionProps) {
    const { t } = useI18n();
    const [selectedGroupId, setSelectedGroupId] = useState<number | null>(() =>
        pickDefaultGroupId(groups, currentUserId),
    );
    const qualifierIds = new Set(
        qualifiers.map((entry) => entry.participant_id),
    );
    const restLabel =
        qualifyPerGroup === 2
            ? t('tournament_best_thirds_label')
            : t('tournament_best_seconds_label');
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
            {qualifiers.length > 0 && (
                <Card>
                    <CardHeader>
                        <CardTitle>{t('tournament_qualifiers')}</CardTitle>
                    </CardHeader>
                    <CardContent className="space-y-3">
                        <p className="text-sm text-muted-foreground">
                            {t('tournament_qualification_summary')
                                .replace('{slots}', `${qualifiers.length}`)
                                .replace(
                                    '{winners}',
                                    `${groups.length * (qualifyPerGroup ?? 1)}`,
                                )
                                .replace('{rest}', `${bestRunnersUp ?? 0}`)
                                .replace('{rest_label}', restLabel)}
                        </p>
                        <div className="flex flex-wrap gap-2">
                            {qualifiers.map((entry) => (
                                <Badge
                                    key={entry.participant_id}
                                    variant="secondary"
                                >
                                    {entry.rank_in_group === 1
                                        ? `${entry.group_name ?? ''} 1.`
                                        : entry.rank_in_group ===
                                            qualifyPerGroup
                                          ? `${entry.group_name ?? ''} ${entry.rank_in_group}.`
                                          : restLabel}
                                    : {entry.name}
                                </Badge>
                            ))}
                        </div>
                    </CardContent>
                </Card>
            )}

            {selectedGroup !== null && (
                <Card>
                    <CardHeader>
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
