import { useMemo, useState } from 'react';
import { buildBracketPreview, shuffleParticipants } from '@/components/league/bracket-utils';
import { TournamentBracket } from '@/components/league/tournament-bracket';
import type {
    KnockoutDrawMode,
    KnockoutParticipantDraft,
    LeagueParticipantMode,
    LeagueUserOption,
} from '@/components/league/types';
import { SearchInput } from '@/components/admin/search-input';
import InputError from '@/components/input-error';
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
import { ArrowDown, ArrowUp, Shuffle } from 'lucide-react';

type KnockoutCreateWizardProps = {
    name: string;
    onNameChange: (value: string) => void;
    setsBestOf: string;
    onSetsBestOfChange: (value: string) => void;
    drawMode: KnockoutDrawMode;
    onDrawModeChange: (value: KnockoutDrawMode) => void;
    participantMode: LeagueParticipantMode;
    onParticipantModeChange: (value: LeagueParticipantMode) => void;
    users: LeagueUserOption[];
    participantIds: number[];
    onParticipantIdsChange: (participantIds: number[]) => void;
    pairs: number[][];
    onPairsChange: (pairs: number[][]) => void;
    errors: Record<string, string[]>;
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

function draftsFromParticipantIds(
    participantIds: number[],
    users: LeagueUserOption[],
): KnockoutParticipantDraft[] {
    const usersById = new Map(users.map((user) => [user.id, user]));

    return participantIds
        .map((id) => usersById.get(id))
        .filter((user): user is LeagueUserOption => user !== undefined)
        .map(userToDraft);
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

export function KnockoutCreateWizard({
    name,
    onNameChange,
    setsBestOf,
    onSetsBestOfChange,
    drawMode,
    onDrawModeChange,
    participantMode,
    onParticipantModeChange,
    users,
    participantIds,
    onParticipantIdsChange,
    pairs,
    onPairsChange,
    errors,
}: KnockoutCreateWizardProps) {
    const { t } = useI18n();
    const [userSearch, setUserSearch] = useState('');
    const [firstPlayerId, setFirstPlayerId] = useState('');
    const [secondPlayerId, setSecondPlayerId] = useState('');

    const isDoubles = participantMode === 'doubles';

    const participants = useMemo(
        () =>
            isDoubles
                ? draftsFromPairs(pairs, users)
                : draftsFromParticipantIds(participantIds, users),
        [isDoubles, pairs, participantIds, users],
    );

    const previewMatches = useMemo(
        () => buildBracketPreview(participants, drawMode),
        [participants, drawMode],
    );

    const takenUserIds = useMemo(() => new Set(pairs.flat()), [pairs]);

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
                `${user.first_name} ${user.last_name}`.toLowerCase().includes(term),
        );
    }, [users, userSearch]);

    function toggleParticipant(userId: number) {
        if (participantIds.includes(userId)) {
            onParticipantIdsChange(participantIds.filter((id) => id !== userId));
            return;
        }

        onParticipantIdsChange([...participantIds, userId]);
    }

    function moveParticipant(index: number, direction: -1 | 1) {
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

        if (target < 0 || target >= participantIds.length) {
            return;
        }

        const next = [...participantIds];
        [next[index], next[target]] = [next[target], next[index]];
        onParticipantIdsChange(next);
    }

    function handleParticipantModeChange(value: LeagueParticipantMode) {
        onParticipantModeChange(value);
        onParticipantIdsChange([]);
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

    return (
        <div className="space-y-4">
            <div className="space-y-2">
                <Label htmlFor="tournament-name">{t('name')}</Label>
                <Input
                    id="tournament-name"
                    value={name}
                    onChange={(event) => onNameChange(event.target.value)}
                    required
                />
                <InputError message={errors.name?.[0]} />
            </div>

            <div className="space-y-2">
                <Label>{t('tournament_sets_best_of')}</Label>
                <Select value={setsBestOf} onValueChange={onSetsBestOfChange}>
                    <SelectTrigger>
                        <SelectValue />
                    </SelectTrigger>
                    <SelectContent>
                        {[1, 3, 5].map((value) => (
                            <SelectItem key={value} value={`${value}`}>
                                {t('tournament_best_of').replace('{count}', `${value}`)}
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
                        handleParticipantModeChange(value as LeagueParticipantMode)
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

            <div className="space-y-2">
                <Label>{t('tournament_draw_mode')}</Label>
                <Select
                    value={drawMode}
                    onValueChange={(value) => onDrawModeChange(value as KnockoutDrawMode)}
                >
                    <SelectTrigger>
                        <SelectValue />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem value="seeded">{t('tournament_draw_seeded')}</SelectItem>
                        <SelectItem value="random">{t('tournament_draw_random')}</SelectItem>
                    </SelectContent>
                </Select>
                <p className="text-xs text-muted-foreground">{t('tournament_draw_mode_hint')}</p>
                <InputError message={errors.knockout_draw_mode?.[0]} />
            </div>

            {isDoubles ? (
                <div className="space-y-3">
                    <Label>{t('tournament_pairs')}</Label>
                    <div className="grid gap-3 sm:grid-cols-2">
                        <div className="space-y-2">
                            <Label>{t('tournament_pair_player_one')}</Label>
                            <Select value={firstPlayerId} onValueChange={setFirstPlayerId}>
                                <SelectTrigger>
                                    <SelectValue placeholder={t('tournament_select_player')} />
                                </SelectTrigger>
                                <SelectContent>
                                    {availablePairUsers
                                        .filter((user) => `${user.id}` !== secondPlayerId)
                                        .map((user) => (
                                            <SelectItem key={user.id} value={`${user.id}`}>
                                                {user.name}
                                            </SelectItem>
                                        ))}
                                </SelectContent>
                            </Select>
                        </div>
                        <div className="space-y-2">
                            <Label>{t('tournament_pair_player_two')}</Label>
                            <Select value={secondPlayerId} onValueChange={setSecondPlayerId}>
                                <SelectTrigger>
                                    <SelectValue placeholder={t('tournament_select_player')} />
                                </SelectTrigger>
                                <SelectContent>
                                    {availablePairUsers
                                        .filter((user) => `${user.id}` !== firstPlayerId)
                                        .map((user) => (
                                            <SelectItem key={user.id} value={`${user.id}`}>
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
                        disabled={firstPlayerId === '' || secondPlayerId === '' || firstPlayerId === secondPlayerId}
                    >
                        {t('tournament_add_pair')}
                    </Button>
                    <InputError message={errors.pairs?.[0]} />
                </div>
            ) : (
                <div className="space-y-2">
                    <Label>{t('league_participants')}</Label>
                    <SearchInput
                        value={userSearch}
                        placeholder={t('search_users_placeholder')}
                        onChange={setUserSearch}
                    />
                    <div className="max-h-48 space-y-2 overflow-y-auto rounded-md border p-3">
                        {filteredUsers.length === 0 ? (
                            <p className="text-sm text-muted-foreground">{t('no_users_match_filter')}</p>
                        ) : (
                            filteredUsers.map((user) => (
                                <label key={user.id} className="flex items-center gap-2 text-sm">
                                    <input
                                        type="checkbox"
                                        checked={participantIds.includes(user.id)}
                                        onChange={() => toggleParticipant(user.id)}
                                    />
                                    <span>
                                        {user.name} ({user.email})
                                    </span>
                                </label>
                            ))
                        )}
                    </div>
                    <InputError message={errors.participant_ids?.[0]} />
                </div>
            )}

            {participants.length > 0 && (
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
                                    : onParticipantIdsChange(shuffleParticipants(participantIds))
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
                                <span className="w-6 shrink-0 text-muted-foreground">{index + 1}.</span>
                                <span className="min-w-0 flex-1 truncate font-medium">
                                    {participant.display_name}
                                </span>
                                {isDoubles && (
                                    <Button
                                        type="button"
                                        variant="ghost"
                                        size="sm"
                                        onClick={() => removePair(index)}
                                    >
                                        {t('tournament_remove_pair')}
                                    </Button>
                                )}
                                <Button
                                    type="button"
                                    variant="ghost"
                                    size="icon"
                                    onClick={() => moveParticipant(index, -1)}
                                    disabled={index === 0}
                                    aria-label={t('tournament_move_up')}
                                >
                                    <ArrowUp className="size-4" />
                                </Button>
                                <Button
                                    type="button"
                                    variant="ghost"
                                    size="icon"
                                    onClick={() => moveParticipant(index, 1)}
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

            {previewMatches.length > 0 && <TournamentBracket previewMatches={previewMatches} />}
        </div>
    );
}
