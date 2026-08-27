import { ArrowDown, ArrowUp, Shuffle } from 'lucide-react';
import { useEffect, useMemo, useState } from 'react';
import { SearchInput } from '@/components/admin/search-input';
import InputError from '@/components/input-error';
import {
    buildBracketPreview,
    shuffleParticipants,
} from '@/components/league/bracket-utils';
import {
    distributeSnake,
    groupLetters,
    groupPairings,
    summarizeQualification,
} from '@/components/league/group-utils';
import { TournamentBracket } from '@/components/league/tournament-bracket';
import type {
    KnockoutDrawMode,
    KnockoutParticipantDraft,
    LeagueParticipantMode,
    LeagueUserOption,
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
    pairs?: number[][];
    participants?: Array<{
        user_id?: number;
        first_name?: string;
        last_name?: string;
    }>;
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
    pairs: number[][];
    onPairsChange: (pairs: number[][]) => void;
    errors: Record<string, string[]>;
    onReadyChange: (state: { canSubmit: boolean; isLastStep: boolean }) => void;
    onPayloadChange: (payload: TournamentWizardPayload) => void;
};

function userToDraft(user: LeagueUserOption): KnockoutParticipantDraft {
    return {
        key: `u-${user.id}`,
        user_id: user.id,
        first_name: user.first_name,
        last_name: user.last_name,
        display_name: user.name,
    };
}

function pairDisplayName(
    pair: number[],
    usersById: Map<number, LeagueUserOption>,
): string {
    const first = usersById.get(pair[0] ?? 0);
    const second = usersById.get(pair[1] ?? 0);

    return `${first?.name ?? ''} / ${second?.name ?? ''}`.trim();
}

function draftsFromPairs(
    pairs: number[][],
    users: LeagueUserOption[],
): KnockoutParticipantDraft[] {
    const usersById = new Map(users.map((user) => [user.id, user]));

    return pairs.map((pair, index) => {
        const first = usersById.get(pair[0] ?? 0);

        return {
            key: `p-${pair[0]}-${pair[1]}-${index}`,
            user_id: pair[0] ?? null,
            first_name: first?.first_name ?? '',
            last_name: first?.last_name ?? '',
            display_name: pairDisplayName(pair, usersById),
        };
    });
}

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
    const isDoubles = !isGroupFormat && participantMode === 'doubles';
    const lastStep = isGroupFormat ? 3 : 2;
    const [step, setStep] = useState(0);
    const [userSearch, setUserSearch] = useState('');
    const [firstPlayerId, setFirstPlayerId] = useState('');
    const [secondPlayerId, setSecondPlayerId] = useState('');
    const [guestFirstName, setGuestFirstName] = useState('');
    const [guestLastName, setGuestLastName] = useState('');
    const [players, setPlayers] = useState<KnockoutParticipantDraft[]>([]);
    const [groupCount, setGroupCount] = useState(3);
    const [groupAssignments, setGroupAssignments] = useState<number[][]>(() =>
        distributeSnake(0, 3),
    );
    const [qualifyPerGroup, setQualifyPerGroup] = useState<1 | 2>(1);
    const [bestRunnersUp, setBestRunnersUp] = useState(isGroupFormat ? 1 : 0);

    function updatePlayers(nextPlayers: KnockoutParticipantDraft[]) {
        setPlayers(nextPlayers);
        setGroupAssignments(distributeSnake(nextPlayers.length, groupCount));
    }

    function updateGroupCount(nextCount: number) {
        setGroupCount(nextCount);
        setGroupAssignments(distributeSnake(players.length, nextCount));
    }

    const pairDrafts = useMemo(
        () => draftsFromPairs(pairs, users),
        [pairs, users],
    );
    const singlesDrafts = players;
    const participants = isDoubles ? pairDrafts : singlesDrafts;

    const previewMatches = useMemo(
        () =>
            isGroupFormat ? [] : buildBracketPreview(participants, drawMode),
        [drawMode, isGroupFormat, participants],
    );

    const takenUserIds = useMemo(() => new Set(pairs.flat()), [pairs]);
    const selectedUserIds = useMemo(
        () =>
            new Set(
                players
                    .map((player) => player.user_id)
                    .filter((id): id is number => id !== null),
            ),
        [players],
    );

    const availablePairUsers = useMemo(
        () => users.filter((user) => !takenUserIds.has(user.id)),
        [users, takenUserIds],
    );

    const filteredUsers = useMemo(() => {
        const term = userSearch.trim().toLowerCase();

        if (term === '') {
            return users;
        }

        return users.filter(
            (user) =>
                user.name.toLowerCase().includes(term) ||
                user.email.toLowerCase().includes(term) ||
                `${user.first_name} ${user.last_name}`
                    .toLowerCase()
                    .includes(term),
        );
    }, [users, userSearch]);

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

    const canProceedFromPlayers = isDoubles
        ? pairs.length >= 2
        : players.length >= 2;
    const canProceedFromGroups =
        isGroupFormat && qualification.isValid && players.length >= 4;
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
            participant_mode: isGroupFormat ? 'singles' : participantMode,
            sets_best_of: Number.parseInt(setsBestOf, 10),
            knockout_draw_mode: drawMode,
        };

        if (isDoubles) {
            payload.pairs = pairs;
        } else {
            payload.participants = players.map((player) =>
                player.user_id !== null
                    ? { user_id: player.user_id }
                    : {
                          first_name: player.first_name,
                          last_name: player.last_name,
                      },
            );
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

    function toggleRegisteredPlayer(user: LeagueUserOption) {
        if (selectedUserIds.has(user.id)) {
            updatePlayers(
                players.filter((player) => player.user_id !== user.id),
            );
            return;
        }

        updatePlayers([...players, userToDraft(user)]);
    }

    function addGuest() {
        const firstName = guestFirstName.trim();
        const lastName = guestLastName.trim();

        if (firstName === '' || lastName === '') {
            return;
        }

        updatePlayers([
            ...players,
            {
                key: `g-${Date.now()}-${players.length}`,
                user_id: null,
                first_name: firstName,
                last_name: lastName,
                display_name: `${firstName} ${lastName}`,
            },
        ]);
        setGuestFirstName('');
        setGuestLastName('');
    }

    function removePlayer(key: string) {
        updatePlayers(players.filter((player) => player.key !== key));
    }

    function movePlayer(index: number, direction: -1 | 1) {
        const target = index + direction;

        if (isDoubles) {
            if (target < 0 || target >= pairs.length) {
                return;
            }

            const next = [...pairs];
            [next[index], next[target]] = [next[target], next[index]];
            onPairsChange(next);

            return;
        }

        if (target < 0 || target >= players.length) {
            return;
        }

        const next = [...players];
        [next[index], next[target]] = [next[target], next[index]];
        setPlayers(next);
    }

    function handleParticipantModeChange(value: LeagueParticipantMode) {
        onParticipantModeChange(value);
        updatePlayers([]);
        onPairsChange([]);
        setFirstPlayerId('');
        setSecondPlayerId('');
    }

    function addPair() {
        const firstId = Number.parseInt(firstPlayerId, 10);
        const secondId = Number.parseInt(secondPlayerId, 10);

        if (
            Number.isNaN(firstId) ||
            Number.isNaN(secondId) ||
            firstId === secondId ||
            takenUserIds.has(firstId) ||
            takenUserIds.has(secondId)
        ) {
            return;
        }

        onPairsChange([...pairs, [firstId, secondId]]);
        setFirstPlayerId('');
        setSecondPlayerId('');
    }

    function removePair(index: number) {
        onPairsChange(pairs.filter((_, pairIndex) => pairIndex !== index));
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
            <div className="flex flex-wrap gap-2">
                {stepLabels.map((label, index) => (
                    <Button
                        key={label}
                        type="button"
                        size="sm"
                        variant={step === index ? 'default' : 'outline'}
                        onClick={() => {
                            if (index <= step || canGoNext()) {
                                setStep(index);
                            }
                        }}
                    >
                        {index + 1}. {label}
                    </Button>
                ))}
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

                    {!isGroupFormat && (
                        <>
                            <div className="space-y-2">
                                <Label>
                                    {t('tournament_participant_mode')}
                                </Label>
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
                                            {t(
                                                'tournament_participant_mode_singles',
                                            )}
                                        </SelectItem>
                                        <SelectItem value="doubles">
                                            {t(
                                                'tournament_participant_mode_doubles',
                                            )}
                                        </SelectItem>
                                    </SelectContent>
                                </Select>
                            </div>

                            <div className="space-y-2">
                                <Label>{t('tournament_draw_mode')}</Label>
                                <Select
                                    value={drawMode}
                                    onValueChange={(value) =>
                                        onDrawModeChange(
                                            value as KnockoutDrawMode,
                                        )
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
                        </>
                    )}
                </div>
            )}

            {step === 1 && isDoubles && (
                <div className="space-y-3">
                    <Label>{t('tournament_pairs')}</Label>
                    <div className="grid gap-3 sm:grid-cols-2">
                        <div className="space-y-2">
                            <Label>{t('tournament_pair_player_one')}</Label>
                            <Select
                                value={firstPlayerId}
                                onValueChange={setFirstPlayerId}
                            >
                                <SelectTrigger>
                                    <SelectValue
                                        placeholder={t(
                                            'tournament_select_player',
                                        )}
                                    />
                                </SelectTrigger>
                                <SelectContent>
                                    {availablePairUsers
                                        .filter(
                                            (user) =>
                                                `${user.id}` !== secondPlayerId,
                                        )
                                        .map((user) => (
                                            <SelectItem
                                                key={user.id}
                                                value={`${user.id}`}
                                            >
                                                {user.name}
                                            </SelectItem>
                                        ))}
                                </SelectContent>
                            </Select>
                        </div>
                        <div className="space-y-2">
                            <Label>{t('tournament_pair_player_two')}</Label>
                            <Select
                                value={secondPlayerId}
                                onValueChange={setSecondPlayerId}
                            >
                                <SelectTrigger>
                                    <SelectValue
                                        placeholder={t(
                                            'tournament_select_player',
                                        )}
                                    />
                                </SelectTrigger>
                                <SelectContent>
                                    {availablePairUsers
                                        .filter(
                                            (user) =>
                                                `${user.id}` !== firstPlayerId,
                                        )
                                        .map((user) => (
                                            <SelectItem
                                                key={user.id}
                                                value={`${user.id}`}
                                            >
                                                {user.name}
                                            </SelectItem>
                                        ))}
                                </SelectContent>
                            </Select>
                        </div>
                    </div>
                    <Button
                        type="button"
                        variant="outline"
                        onClick={addPair}
                        disabled={
                            firstPlayerId === '' ||
                            secondPlayerId === '' ||
                            firstPlayerId === secondPlayerId
                        }
                    >
                        {t('tournament_add_pair')}
                    </Button>
                    <InputError message={errors.pairs?.[0]} />
                </div>
            )}

            {step === 1 && !isDoubles && (
                <div className="space-y-4">
                    <div className="space-y-2">
                        <Label>{t('league_participants')}</Label>
                        <SearchInput
                            value={userSearch}
                            placeholder={t('search_users_placeholder')}
                            onChange={setUserSearch}
                        />
                        <div className="max-h-48 space-y-2 overflow-y-auto rounded-md border p-3">
                            {filteredUsers.length === 0 ? (
                                <p className="text-sm text-muted-foreground">
                                    {t('no_users_match_filter')}
                                </p>
                            ) : (
                                filteredUsers.map((user) => (
                                    <label
                                        key={user.id}
                                        className="flex items-center gap-2 text-sm"
                                    >
                                        <input
                                            type="checkbox"
                                            checked={selectedUserIds.has(
                                                user.id,
                                            )}
                                            onChange={() =>
                                                toggleRegisteredPlayer(user)
                                            }
                                        />
                                        <span>
                                            {user.name} ({user.email})
                                        </span>
                                    </label>
                                ))
                            )}
                        </div>
                    </div>

                    <div className="space-y-2 rounded-md border p-3">
                        <Label>{t('tournament_add_guest')}</Label>
                        <div className="grid gap-3 sm:grid-cols-2">
                            <Input
                                value={guestFirstName}
                                placeholder={t('tournament_guest_first_name')}
                                onChange={(event) =>
                                    setGuestFirstName(event.target.value)
                                }
                            />
                            <Input
                                value={guestLastName}
                                placeholder={t('tournament_guest_last_name')}
                                onChange={(event) =>
                                    setGuestLastName(event.target.value)
                                }
                            />
                        </div>
                        <Button
                            type="button"
                            variant="outline"
                            onClick={addGuest}
                            disabled={
                                guestFirstName.trim() === '' ||
                                guestLastName.trim() === ''
                            }
                        >
                            {t('tournament_add_guest')}
                        </Button>
                    </div>
                    <InputError
                        message={
                            errors.participants?.[0] ??
                            errors.participant_ids?.[0]
                        }
                    />
                </div>
            )}

            {step === 1 && participants.length > 0 && (
                <div className="space-y-2">
                    <div className="flex items-center justify-between gap-2">
                        <Label>{t('tournament_seed_order')}</Label>
                        <Button
                            type="button"
                            variant="outline"
                            size="sm"
                            onClick={() =>
                                isDoubles
                                    ? onPairsChange(shuffleParticipants(pairs))
                                    : setPlayers(shuffleParticipants(players))
                            }
                        >
                            <Shuffle className="mr-1 size-4" />
                            {t('tournament_shuffle')}
                        </Button>
                    </div>
                    <div className="space-y-2">
                        {participants.map((participant, index) => (
                            <div
                                key={participant.key}
                                className="flex items-center gap-2 rounded-md border px-3 py-2 text-sm"
                            >
                                <span className="w-6 shrink-0 text-muted-foreground">
                                    {index + 1}.
                                </span>
                                <span className="min-w-0 flex-1 truncate font-medium">
                                    {participant.display_name}
                                    {participant.user_id === null &&
                                    !isDoubles ? (
                                        <span className="ml-2 text-xs text-muted-foreground">
                                            {t('tournament_guest_badge')}
                                        </span>
                                    ) : null}
                                </span>
                                {isDoubles ? (
                                    <Button
                                        type="button"
                                        variant="ghost"
                                        size="sm"
                                        onClick={() => removePair(index)}
                                    >
                                        {t('tournament_remove_pair')}
                                    </Button>
                                ) : (
                                    <Button
                                        type="button"
                                        variant="ghost"
                                        size="sm"
                                        onClick={() =>
                                            removePlayer(participant.key)
                                        }
                                    >
                                        {t('tournament_remove_player')}
                                    </Button>
                                )}
                                <Button
                                    type="button"
                                    variant="ghost"
                                    size="icon"
                                    onClick={() => movePlayer(index, -1)}
                                    disabled={index === 0}
                                    aria-label={t('tournament_move_up')}
                                >
                                    <ArrowUp className="size-4" />
                                </Button>
                                <Button
                                    type="button"
                                    variant="ghost"
                                    size="icon"
                                    onClick={() => movePlayer(index, 1)}
                                    disabled={index === participants.length - 1}
                                    aria-label={t('tournament_move_down')}
                                >
                                    <ArrowDown className="size-4" />
                                </Button>
                            </div>
                        ))}
                    </div>
                </div>
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
                            >
                                <SelectTrigger>
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    {[2, 3, 4, 5, 6, 8].map((value) => (
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
                                    setQualifyPerGroup(value === '2' ? 2 : 1)
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
                                    setBestRunnersUp(Number.parseInt(value, 10))
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

                    <div className="grid gap-3 md:grid-cols-3">
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
                                    const player = players[playerIndex];

                                    if (player === undefined) {
                                        return null;
                                    }

                                    return (
                                        <div
                                            key={player.key}
                                            className="flex items-center gap-2 text-sm"
                                        >
                                            <span className="min-w-0 flex-1 truncate">
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
                                                <SelectTrigger className="h-8 w-20">
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
                            {groupAssignments.map((indexes, groupIndex) => {
                                const names = indexes
                                    .map(
                                        (playerIndex) =>
                                            players[playerIndex]?.display_name,
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
