import { useEffect, useMemo, useState } from 'react';
import InputError from '@/components/input-error';
import { buildBracketPreview } from '@/components/league/bracket-utils';
import {
    availableGroupCounts,
    clampGroupCount,
    distributeSnake,
    groupLetters,
    groupPairings,
    minPlayersPerGroup,
    summarizeQualification,
} from '@/components/league/group-utils';
import {
    LeagueParticipantPicker,
    pairDisplayName,
    pairToPayload,
    singlesToParticipants,
} from '@/components/league/league-participant-picker';
import { TournamentBracket } from '@/components/league/tournament-bracket';
import type {
    KnockoutDrawMode,
    KnockoutParticipantDraft,
    LeagueParticipantMode,
    LeagueUserOption,
    PairDraft,
    TournamentCreateParticipant,
    TournamentCreatePair,
    TournamentKind,
} from '@/components/league/types';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { useI18n } from '@/lib/i18n';

export type TournamentWizardPayload = {
    name: string;
    format: TournamentKind;
    participant_mode: LeagueParticipantMode;
    sets_best_of: number;
    knockout_draw_mode?: KnockoutDrawMode;
    pairs?: TournamentCreatePair[];
    participants?: TournamentCreateParticipant[];
    qualify_per_group?: number;
    best_runners_up?: number;
    groups?: Array<{ name: string; participant_indexes: number[] }>;
};

type TournamentCreateWizardProps = {
    format: TournamentKind;
    name: string;
    onNameChange: (value: string) => void;
    setsBestOf: string;
    onSetsBestOfChange: (value: string) => void;
    drawMode: KnockoutDrawMode;
    onDrawModeChange: (value: KnockoutDrawMode) => void;
    participantMode: LeagueParticipantMode;
    onParticipantModeChange: (value: LeagueParticipantMode) => void;
    users: LeagueUserOption[];
    pairs: PairDraft[];
    onPairsChange: (pairs: PairDraft[]) => void;
    errors: Record<string, string[]>;
    onReadyChange: (state: { canSubmit: boolean; isLastStep: boolean }) => void;
    onPayloadChange: (payload: TournamentWizardPayload) => void;
};

export function KnockoutCreateWizard(props: TournamentCreateWizardProps) {
    return <TournamentCreateWizard {...props} />;
}

export function TournamentCreateWizard({
    format,
    name,
    onNameChange,
    setsBestOf,
    onSetsBestOfChange,
    drawMode,
    onDrawModeChange,
    participantMode,
    onParticipantModeChange,
    users,
    pairs,
    onPairsChange,
    errors,
    onReadyChange,
    onPayloadChange,
}: TournamentCreateWizardProps) {
    const { t } = useI18n();
    const isGroupFormat = format === 'group_knockout';
    const isDoubles = participantMode === 'doubles';
    const lastStep = isGroupFormat ? 3 : 2;
    const [step, setStep] = useState(0);
    const [players, setPlayers] = useState<KnockoutParticipantDraft[]>([]);
    const [groupCount, setGroupCount] = useState(3);
    const [groupAssignments, setGroupAssignments] = useState<number[][]>(() =>
        distributeSnake(0, 3),
    );
    const [qualifyPerGroup, setQualifyPerGroup] = useState<1 | 2>(1);
    const [bestRunnersUp, setBestRunnersUp] = useState(isGroupFormat ? 1 : 0);

    const participantCount = isDoubles ? pairs.length : players.length;
    const playersPerGroupMinimum = minPlayersPerGroup(
        qualifyPerGroup,
        bestRunnersUp,
    );
    const groupCountOptions = availableGroupCounts(
        participantCount,
        playersPerGroupMinimum,
    );
    const canSelectGroupCount = groupCountOptions.length > 0;

    function applyGroupLayout(
        nextPlayerCount: number,
        nextGroupCount = groupCount,
        nextQualifyPerGroup = qualifyPerGroup,
        nextBestRunnersUp = bestRunnersUp,
    ): void {
        const nextMinPlayers = minPlayersPerGroup(
            nextQualifyPerGroup,
            nextBestRunnersUp,
        );
        const nextCount = clampGroupCount(
            nextGroupCount,
            nextPlayerCount,
            nextMinPlayers,
        );
        const nextRunners = Math.min(Math.max(nextBestRunnersUp, 0), nextCount);

        setGroupCount(nextCount);
        setBestRunnersUp(nextRunners);

        if (nextCount !== groupCount || nextPlayerCount !== participantCount) {
            setGroupAssignments(distributeSnake(nextPlayerCount, nextCount));
        }
    }

    function updatePlayers(nextPlayers: KnockoutParticipantDraft[]) {
        setPlayers(nextPlayers);
        applyGroupLayout(nextPlayers.length);
    }

    function updatePairs(nextPairs: PairDraft[]) {
        onPairsChange(nextPairs);
        applyGroupLayout(nextPairs.length);
    }

    function updateGroupCount(nextCount: number) {
        applyGroupLayout(participantCount, nextCount);
    }

    function updateQualifyPerGroup(nextQualify: 1 | 2) {
        setQualifyPerGroup(nextQualify);
        applyGroupLayout(participantCount, groupCount, nextQualify);
    }

    function updateBestRunnersUp(nextRunners: number) {
        applyGroupLayout(
            participantCount,
            groupCount,
            qualifyPerGroup,
            nextRunners,
        );
    }

    const participants: KnockoutParticipantDraft[] = isDoubles
        ? pairs.map((pair) => ({
              key: pair.key,
              user_id: pair.player_one.user_id,
              first_name: pair.player_one.first_name,
              last_name: pair.player_one.last_name,
              display_name: pairDisplayName(pair),
          }))
        : players;

    const previewMatches = useMemo(
        () =>
            isGroupFormat ? [] : buildBracketPreview(participants, drawMode),
        [drawMode, isGroupFormat, participants],
    );

    const groupNames = useMemo(() => groupLetters(groupCount), [groupCount]);
    const groupSizes = useMemo(
        () => groupAssignments.map((indexes) => indexes.length),
        [groupAssignments],
    );
    const qualification = useMemo(
        () =>
            summarizeQualification(groupSizes, qualifyPerGroup, bestRunnersUp),
        [bestRunnersUp, groupSizes, qualifyPerGroup],
    );

    const canProceedFromPlayers = participantCount >= 2;
    const canProceedFromGroups =
        isGroupFormat && qualification.isValid && participantCount >= 4;
    const canSubmit = isGroupFormat
        ? name.trim() !== '' && canProceedFromGroups
        : name.trim() !== '' && canProceedFromPlayers;

    useEffect(() => {
        onReadyChange({
            canSubmit,
            isLastStep: step === lastStep,
        });
    }, [canSubmit, lastStep, onReadyChange, step]);

    useEffect(() => {
        const payload: TournamentWizardPayload = {
            name,
            format,
            participant_mode: participantMode,
            sets_best_of: Number.parseInt(setsBestOf, 10),
            knockout_draw_mode: drawMode,
        };

        if (isDoubles) {
            payload.pairs = pairs.map(pairToPayload);
        } else {
            payload.participants = singlesToParticipants(players);
        }

        if (isGroupFormat) {
            payload.qualify_per_group = qualifyPerGroup;
            payload.best_runners_up = bestRunnersUp;
            payload.groups = groupAssignments.map((indexes, index) => ({
                name: groupNames[index] ?? `${index + 1}`,
                participant_indexes: indexes,
            }));
        }

        onPayloadChange(payload);
    }, [
        bestRunnersUp,
        drawMode,
        format,
        groupAssignments,
        groupNames,
        isDoubles,
        isGroupFormat,
        name,
        onPayloadChange,
        pairs,
        participantMode,
        players,
        qualifyPerGroup,
        setsBestOf,
    ]);

    function handleParticipantModeChange(value: LeagueParticipantMode) {
        onParticipantModeChange(value);
        updatePlayers([]);
        updatePairs([]);
    }

    function movePlayerToGroup(playerIndex: number, groupIndex: number) {
        setGroupAssignments((current) => {
            const next = current.map((indexes) =>
                indexes.filter((index) => index !== playerIndex),
            );

            if (next[groupIndex] === undefined) {
                return current;
            }

            next[groupIndex] = [...next[groupIndex], playerIndex];

            return next;
        });
    }

    function canGoNext(): boolean {
        if (step === 0) {
            return name.trim() !== '';
        }

        if (step === 1) {
            return canProceedFromPlayers;
        }

        if (step === 2 && isGroupFormat) {
            return canProceedFromGroups;
        }

        return true;
    }

    const stepLabels = isGroupFormat
        ? [
              t('tournament_step_basics'),
              t('tournament_step_players'),
              t('tournament_step_groups'),
              t('tournament_step_preview'),
          ]
        : [
              t('tournament_step_basics'),
              t('tournament_step_players'),
              t('tournament_step_preview'),
          ];

    return (
        <div className="space-y-4">
            <div className="space-y-2">
                <div className="flex gap-1 sm:flex-wrap sm:gap-2">
                    {stepLabels.map((label, index) => (
                        <Button
                            key={label}
                            type="button"
                            size="sm"
                            variant={step === index ? 'default' : 'outline'}
                            className="min-w-0 flex-1 px-2 sm:flex-none sm:px-3"
                            onClick={() => {
                                if (index <= step || canGoNext()) {
                                    setStep(index);
                                }
                            }}
                        >
                            <span className="sm:hidden">{index + 1}</span>
                            <span className="hidden sm:inline">
                                {index + 1}. {label}
                            </span>
                        </Button>
                    ))}
                </div>
                <p className="text-sm text-muted-foreground sm:hidden">
                    {stepLabels[step]}
                </p>
            </div>

            {step === 0 && (
                <div className="space-y-4">
                    <div className="space-y-2">
                        <Label htmlFor="tournament-name">{t('name')}</Label>
                        <Input
                            id="tournament-name"
                            value={name}
                            onChange={(event) =>
                                onNameChange(event.target.value)
                            }
                            required
                        />
                        <InputError message={errors.name?.[0]} />
                    </div>

                    <div className="space-y-2">
                        <Label>{t('tournament_sets_best_of')}</Label>
                        <Select
                            value={setsBestOf}
                            onValueChange={onSetsBestOfChange}
                        >
                            <SelectTrigger>
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                {[1, 3, 5].map((value) => (
                                    <SelectItem key={value} value={`${value}`}>
                                        {t('tournament_best_of').replace(
                                            '{count}',
                                            `${value}`,
                                        )}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                        <InputError message={errors.sets_best_of?.[0]} />
                    </div>

                    <div className="space-y-2">
                        <Label>{t('tournament_participant_mode')}</Label>
                        <Select
                            value={participantMode}
                            onValueChange={(value) =>
                                handleParticipantModeChange(
                                    value as LeagueParticipantMode,
                                )
                            }
                        >
                            <SelectTrigger>
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="singles">
                                    {t('tournament_participant_mode_singles')}
                                </SelectItem>
                                <SelectItem value="doubles">
                                    {t('tournament_participant_mode_doubles')}
                                </SelectItem>
                            </SelectContent>
                        </Select>
                    </div>

                    {!isGroupFormat && (
                        <div className="space-y-2">
                            <Label>{t('tournament_draw_mode')}</Label>
                            <Select
                                value={drawMode}
                                onValueChange={(value) =>
                                    onDrawModeChange(value as KnockoutDrawMode)
                                }
                            >
                                <SelectTrigger>
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="seeded">
                                        {t('tournament_draw_seeded')}
                                    </SelectItem>
                                    <SelectItem value="random">
                                        {t('tournament_draw_random')}
                                    </SelectItem>
                                </SelectContent>
                            </Select>
                            <p className="text-xs text-muted-foreground">
                                {t('tournament_draw_mode_hint')}
                            </p>
                            <InputError
                                message={errors.knockout_draw_mode?.[0]}
                            />
                        </div>
                    )}
                </div>
            )}

            {step === 1 && (
                <LeagueParticipantPicker
                    mode={participantMode}
                    users={users}
                    players={players}
                    onPlayersChange={updatePlayers}
                    pairs={pairs}
                    onPairsChange={updatePairs}
                    errors={errors}
                />
            )}

            {step === 2 && isGroupFormat && (
                <div className="space-y-4">
                    <div className="grid gap-3 sm:grid-cols-3">
                        <div className="space-y-2">
                            <Label>{t('tournament_group_count')}</Label>
                            <Select
                                value={`${groupCount}`}
                                onValueChange={(value) =>
                                    updateGroupCount(Number.parseInt(value, 10))
                                }
                                disabled={!canSelectGroupCount}
                            >
                                <SelectTrigger>
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    {(canSelectGroupCount
                                        ? groupCountOptions
                                        : [groupCount]
                                    ).map((value) => (
                                        <SelectItem
                                            key={value}
                                            value={`${value}`}
                                        >
                                            {value}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                        </div>
                        <div className="space-y-2">
                            <Label>{t('tournament_qualify_per_group')}</Label>
                            <Select
                                value={`${qualifyPerGroup}`}
                                onValueChange={(value) =>
                                    updateQualifyPerGroup(value === '2' ? 2 : 1)
                                }
                            >
                                <SelectTrigger>
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="1">
                                        {t('tournament_qualify_first')}
                                    </SelectItem>
                                    <SelectItem value="2">
                                        {t('tournament_qualify_first_two')}
                                    </SelectItem>
                                </SelectContent>
                            </Select>
                        </div>
                        <div className="space-y-2">
                            <Label>
                                {qualifyPerGroup === 1
                                    ? t('tournament_best_seconds')
                                    : t('tournament_best_thirds')}
                            </Label>
                            <Select
                                value={`${bestRunnersUp}`}
                                onValueChange={(value) =>
                                    updateBestRunnersUp(
                                        Number.parseInt(value, 10),
                                    )
                                }
                            >
                                <SelectTrigger>
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    {Array.from(
                                        { length: groupCount + 1 },
                                        (_, value) => (
                                            <SelectItem
                                                key={value}
                                                value={`${value}`}
                                            >
                                                {value}
                                            </SelectItem>
                                        ),
                                    )}
                                </SelectContent>
                            </Select>
                        </div>
                    </div>

                    <p className="text-sm text-muted-foreground">
                        {t('tournament_qualification_summary')
                            .replace(
                                '{slots}',
                                `${qualification.knockoutSlots}`,
                            )
                            .replace(
                                '{winners}',
                                `${groupCount * qualifyPerGroup}`,
                            )
                            .replace('{rest}', `${bestRunnersUp}`)
                            .replace(
                                '{rest_label}',
                                qualifyPerGroup === 1
                                    ? t('tournament_best_seconds_label')
                                    : t('tournament_best_thirds_label'),
                            )}
                    </p>
                    {qualification.isUneven && (
                        <p className="text-sm text-amber-600">
                            {t('tournament_groups_uneven')}
                        </p>
                    )}
                    {qualification.errorKey !== null && (
                        <p className="text-sm text-destructive">
                            {qualification.errorKey === 'tournament_groups_min'
                                ? t('tournament_groups_min')
                                : qualification.errorKey ===
                                    'tournament_groups_too_small'
                                  ? t('tournament_groups_too_small')
                                  : qualification.errorKey ===
                                      'tournament_best_runners_too_many'
                                    ? t('tournament_best_runners_too_many')
                                    : t('tournament_knockout_min_players')}
                        </p>
                    )}
                    <InputError message={errors.groups?.[0]} />

                    <div className="grid grid-cols-1 gap-3 sm:grid-cols-2">
                        {groupAssignments.map((indexes, groupIndex) => (
                            <div
                                key={groupNames[groupIndex]}
                                className="space-y-2 rounded-md border p-3"
                            >
                                <p className="font-medium">
                                    {t('tournament_group')}{' '}
                                    {groupNames[groupIndex]}
                                </p>
                                {indexes.map((playerIndex) => {
                                    const player = participants[playerIndex];

                                    if (player === undefined) {
                                        return null;
                                    }

                                    return (
                                        <div
                                            key={player.key}
                                            className="flex flex-col gap-2 text-sm sm:flex-row sm:items-center"
                                        >
                                            <span className="min-w-0 flex-1 break-words">
                                                {player.display_name}
                                            </span>
                                            <Select
                                                value={`${groupIndex}`}
                                                onValueChange={(value) =>
                                                    movePlayerToGroup(
                                                        playerIndex,
                                                        Number.parseInt(
                                                            value,
                                                            10,
                                                        ),
                                                    )
                                                }
                                            >
                                                <SelectTrigger className="h-8 w-full sm:w-24">
                                                    <SelectValue />
                                                </SelectTrigger>
                                                <SelectContent>
                                                    {groupNames.map(
                                                        (letter, index) => (
                                                            <SelectItem
                                                                key={letter}
                                                                value={`${index}`}
                                                            >
                                                                {letter}
                                                            </SelectItem>
                                                        ),
                                                    )}
                                                </SelectContent>
                                            </Select>
                                        </div>
                                    );
                                })}
                            </div>
                        ))}
                    </div>
                </div>
            )}

            {((step === 2 && !isGroupFormat) ||
                (step === 3 && isGroupFormat)) && (
                <div className="space-y-4">
                    {isGroupFormat ? (
                        <div className="space-y-3">
                            <p className="text-sm text-muted-foreground">
                                {t('tournament_qualification_summary')
                                    .replace(
                                        '{slots}',
                                        `${qualification.knockoutSlots}`,
                                    )
                                    .replace(
                                        '{winners}',
                                        `${groupCount * qualifyPerGroup}`,
                                    )
                                    .replace('{rest}', `${bestRunnersUp}`)
                                    .replace(
                                        '{rest_label}',
                                        qualifyPerGroup === 1
                                            ? t('tournament_best_seconds_label')
                                            : t('tournament_best_thirds_label'),
                                    )}
                            </p>
                            <div className="grid grid-cols-1 gap-3 sm:grid-cols-2">
                                {groupAssignments.map((indexes, groupIndex) => {
                                    const names = indexes
                                        .map(
                                            (playerIndex) =>
                                                participants[playerIndex]
                                                    ?.display_name,
                                        )
                                        .filter(
                                            (value): value is string =>
                                                value !== undefined,
                                        );

                                    return (
                                        <div
                                            key={groupNames[groupIndex]}
                                            className="rounded-md border p-3"
                                        >
                                            <p className="mb-2 font-medium">
                                                {t('tournament_group')}{' '}
                                                {groupNames[groupIndex]}
                                            </p>
                                            <ul className="space-y-1 text-sm">
                                                {groupPairings(names).map(
                                                    ([first, second]) => (
                                                        <li
                                                            key={`${first}-${second}`}
                                                            className="break-words"
                                                        >
                                                            {first} vs {second}
                                                        </li>
                                                    ),
                                                )}
                                            </ul>
                                        </div>
                                    );
                                })}
                            </div>
                        </div>
                    ) : (
                        previewMatches.length > 0 && (
                            <TournamentBracket
                                previewMatches={previewMatches}
                            />
                        )
                    )}
                </div>
            )}

            <div className="flex justify-between gap-2">
                <Button
                    type="button"
                    variant="outline"
                    disabled={step === 0}
                    onClick={() => setStep((current) => current - 1)}
                >
                    {t('tournament_step_back')}
                </Button>
                {step < lastStep && (
                    <Button
                        type="button"
                        disabled={!canGoNext()}
                        onClick={() => setStep((current) => current + 1)}
                    >
                        {t('tournament_step_next')}
                    </Button>
                )}
            </div>
        </div>
    );
}
