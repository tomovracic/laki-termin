import { ArrowDown, ArrowUp, Shuffle } from 'lucide-react';
import { useMemo, useState } from 'react';
import { SearchInput } from '@/components/admin/search-input';
import InputError from '@/components/input-error';
import { shuffleParticipants } from '@/components/league/bracket-utils';
import type {
    KnockoutParticipantDraft,
    LeagueParticipantMode,
    LeagueUserOption,
    PairDraft,
    PairPlayerDraft,
    TournamentCreateParticipant,
    TournamentCreatePair,
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

export function userToDraft(user: LeagueUserOption): KnockoutParticipantDraft {
    return {
        key: `u-${user.id}`,
        user_id: user.id,
        first_name: user.first_name,
        last_name: user.last_name,
        display_name: user.name,
    };
}

export function userToPairPlayer(user: LeagueUserOption): PairPlayerDraft {
    return {
        user_id: user.id,
        first_name: user.first_name,
        last_name: user.last_name,
        display_name: user.name,
    };
}

export function guestToPairPlayer(
    firstName: string,
    lastName: string,
): PairPlayerDraft {
    return {
        user_id: null,
        first_name: firstName,
        last_name: lastName,
        display_name: `${firstName} ${lastName}`,
    };
}

export function pairDisplayName(pair: PairDraft): string {
    return `${pair.player_one.display_name} / ${pair.player_two.display_name}`;
}

export function pairToPayload(pair: PairDraft): TournamentCreatePair {
    return {
        player_one: draftToParticipant(pair.player_one),
        player_two: draftToParticipant(pair.player_two),
    };
}

export function draftToParticipant(
    player: Pick<PairPlayerDraft, 'user_id' | 'first_name' | 'last_name'>,
): TournamentCreateParticipant {
    return player.user_id !== null
        ? { user_id: player.user_id }
        : { first_name: player.first_name, last_name: player.last_name };
}

export function singlesToParticipants(
    players: KnockoutParticipantDraft[],
): TournamentCreateParticipant[] {
    return players.map((player) => draftToParticipant(player));
}

type PlayerSlotState = {
    mode: 'user' | 'guest';
    userId: string;
    firstName: string;
    lastName: string;
};

function emptySlot(): PlayerSlotState {
    return { mode: 'user', userId: '', firstName: '', lastName: '' };
}

function slotToPlayer(
    slot: PlayerSlotState,
    usersById: Map<number, LeagueUserOption>,
): PairPlayerDraft | null {
    if (slot.mode === 'user') {
        const userId = Number.parseInt(slot.userId, 10);

        if (Number.isNaN(userId)) {
            return null;
        }

        const user = usersById.get(userId);

        if (user === undefined) {
            return null;
        }

        return userToPairPlayer(user);
    }

    const firstName = slot.firstName.trim();
    const lastName = slot.lastName.trim();

    if (firstName === '' || lastName === '') {
        return null;
    }

    return guestToPairPlayer(firstName, lastName);
}

type PlayerSlotInputProps = {
    label: string;
    slot: PlayerSlotState;
    onChange: (slot: PlayerSlotState) => void;
    users: LeagueUserOption[];
    excludedUserIds: Set<number>;
};

export function PlayerSlotInput({
    label,
    slot,
    onChange,
    users,
    excludedUserIds,
}: PlayerSlotInputProps) {
    const { t } = useI18n();

    return (
        <div className="space-y-2">
            <Label>{label}</Label>
            <Select
                value={slot.mode}
                onValueChange={(value) =>
                    onChange({
                        ...emptySlot(),
                        mode: value === 'guest' ? 'guest' : 'user',
                    })
                }
            >
                <SelectTrigger>
                    <SelectValue />
                </SelectTrigger>
                <SelectContent>
                    <SelectItem value="user">
                        {t('tournament_slot_registered')}
                    </SelectItem>
                    <SelectItem value="guest">
                        {t('tournament_slot_guest')}
                    </SelectItem>
                </SelectContent>
            </Select>
            {slot.mode === 'user' ? (
                <Select
                    value={slot.userId}
                    onValueChange={(value) =>
                        onChange({ ...slot, userId: value })
                    }
                >
                    <SelectTrigger>
                        <SelectValue
                            placeholder={t('tournament_select_player')}
                        />
                    </SelectTrigger>
                    <SelectContent>
                        {users
                            .filter((user) => !excludedUserIds.has(user.id))
                            .map((user) => (
                                <SelectItem key={user.id} value={`${user.id}`}>
                                    {user.name}
                                </SelectItem>
                            ))}
                    </SelectContent>
                </Select>
            ) : (
                <div className="grid gap-2 sm:grid-cols-2">
                    <Input
                        value={slot.firstName}
                        placeholder={t('tournament_guest_first_name')}
                        onChange={(event) =>
                            onChange({ ...slot, firstName: event.target.value })
                        }
                    />
                    <Input
                        value={slot.lastName}
                        placeholder={t('tournament_guest_last_name')}
                        onChange={(event) =>
                            onChange({ ...slot, lastName: event.target.value })
                        }
                    />
                </div>
            )}
        </div>
    );
}

type LeagueParticipantPickerProps = {
    mode: LeagueParticipantMode;
    users: LeagueUserOption[];
    players: KnockoutParticipantDraft[];
    onPlayersChange: (players: KnockoutParticipantDraft[]) => void;
    pairs: PairDraft[];
    onPairsChange: (pairs: PairDraft[]) => void;
    showSeedOrder?: boolean;
    errors?: Record<string, string[]>;
};

export function LeagueParticipantPicker({
    mode,
    users,
    players,
    onPlayersChange,
    pairs,
    onPairsChange,
    showSeedOrder = true,
    errors = {},
}: LeagueParticipantPickerProps) {
    const { t } = useI18n();
    const isDoubles = mode === 'doubles';
    const [userSearch, setUserSearch] = useState('');
    const [guestFirstName, setGuestFirstName] = useState('');
    const [guestLastName, setGuestLastName] = useState('');
    const [firstSlot, setFirstSlot] = useState<PlayerSlotState>(emptySlot);
    const [secondSlot, setSecondSlot] = useState<PlayerSlotState>(emptySlot);

    const usersById = useMemo(
        () => new Map(users.map((user) => [user.id, user])),
        [users],
    );

    const takenUserIds = useMemo(() => {
        if (isDoubles) {
            return new Set(
                pairs.flatMap((pair) =>
                    [pair.player_one.user_id, pair.player_two.user_id].filter(
                        (id): id is number => id !== null,
                    ),
                ),
            );
        }

        return new Set(
            players
                .map((player) => player.user_id)
                .filter((id): id is number => id !== null),
        );
    }, [isDoubles, pairs, players]);

    const firstSlotUserId =
        firstSlot.mode === 'user' ? Number.parseInt(firstSlot.userId, 10) : NaN;
    const secondSlotUserId =
        secondSlot.mode === 'user'
            ? Number.parseInt(secondSlot.userId, 10)
            : NaN;

    const firstExcluded = useMemo(() => {
        const excluded = new Set(takenUserIds);

        if (!Number.isNaN(secondSlotUserId)) {
            excluded.add(secondSlotUserId);
        }

        return excluded;
    }, [secondSlotUserId, takenUserIds]);

    const secondExcluded = useMemo(() => {
        const excluded = new Set(takenUserIds);

        if (!Number.isNaN(firstSlotUserId)) {
            excluded.add(firstSlotUserId);
        }

        return excluded;
    }, [firstSlotUserId, takenUserIds]);

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
    }, [userSearch, users]);

    const selectedUserIds = useMemo(
        () =>
            new Set(
                players
                    .map((player) => player.user_id)
                    .filter((id): id is number => id !== null),
            ),
        [players],
    );

    const entries = isDoubles
        ? pairs.map((pair) => ({
              key: pair.key,
              display_name: pairDisplayName(pair),
              is_guest:
                  pair.player_one.user_id === null ||
                  pair.player_two.user_id === null,
          }))
        : players.map((player) => ({
              key: player.key,
              display_name: player.display_name,
              is_guest: player.user_id === null,
          }));

    function toggleRegisteredPlayer(user: LeagueUserOption) {
        if (selectedUserIds.has(user.id)) {
            onPlayersChange(
                players.filter((player) => player.user_id !== user.id),
            );
            return;
        }

        onPlayersChange([...players, userToDraft(user)]);
    }

    function addGuest() {
        const firstName = guestFirstName.trim();
        const lastName = guestLastName.trim();

        if (firstName === '' || lastName === '') {
            return;
        }

        onPlayersChange([
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

    function addPair() {
        const first = slotToPlayer(firstSlot, usersById);
        const second = slotToPlayer(secondSlot, usersById);

        if (first === null || second === null) {
            return;
        }

        if (
            first.user_id !== null &&
            second.user_id !== null &&
            first.user_id === second.user_id
        ) {
            return;
        }

        if (
            (first.user_id !== null && takenUserIds.has(first.user_id)) ||
            (second.user_id !== null && takenUserIds.has(second.user_id))
        ) {
            return;
        }

        onPairsChange([
            ...pairs,
            {
                key: `p-${Date.now()}-${pairs.length}`,
                player_one: first,
                player_two: second,
            },
        ]);
        setFirstSlot(emptySlot());
        setSecondSlot(emptySlot());
    }

    function moveEntry(index: number, direction: -1 | 1) {
        const target = index + direction;
        const source = isDoubles ? pairs : players;

        if (target < 0 || target >= source.length) {
            return;
        }

        if (isDoubles) {
            const next = [...pairs];
            [next[index], next[target]] = [next[target], next[index]];
            onPairsChange(next);
            return;
        }

        const next = [...players];
        [next[index], next[target]] = [next[target], next[index]];
        onPlayersChange(next);
    }

    const canAddPair =
        slotToPlayer(firstSlot, usersById) !== null &&
        slotToPlayer(secondSlot, usersById) !== null;

    return (
        <div className="space-y-4">
            {isDoubles ? (
                <div className="space-y-3">
                    <Label>{t('tournament_pairs')}</Label>
                    <div className="grid gap-3 sm:grid-cols-2">
                        <PlayerSlotInput
                            label={t('tournament_pair_player_one')}
                            slot={firstSlot}
                            onChange={setFirstSlot}
                            users={users}
                            excludedUserIds={firstExcluded}
                        />
                        <PlayerSlotInput
                            label={t('tournament_pair_player_two')}
                            slot={secondSlot}
                            onChange={setSecondSlot}
                            users={users}
                            excludedUserIds={secondExcluded}
                        />
                    </div>
                    <Button
                        type="button"
                        variant="outline"
                        onClick={addPair}
                        disabled={!canAddPair}
                    >
                        {t('tournament_add_pair')}
                    </Button>
                    <InputError message={errors.pairs?.[0]} />
                </div>
            ) : (
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

            {showSeedOrder && entries.length > 0 && (
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
                                    : onPlayersChange(
                                          shuffleParticipants(players),
                                      )
                            }
                        >
                            <Shuffle className="mr-1 size-4" />
                            {t('tournament_shuffle')}
                        </Button>
                    </div>
                    <div className="space-y-2">
                        {entries.map((entry, index) => (
                            <div
                                key={entry.key}
                                className="flex items-center gap-2 rounded-md border px-3 py-2 text-sm"
                            >
                                <span className="w-6 shrink-0 text-muted-foreground">
                                    {index + 1}.
                                </span>
                                <span className="min-w-0 flex-1 truncate font-medium">
                                    {entry.display_name}
                                    {entry.is_guest ? (
                                        <span className="ml-2 text-xs text-muted-foreground">
                                            {t('tournament_guest_badge')}
                                        </span>
                                    ) : null}
                                </span>
                                <Button
                                    type="button"
                                    variant="ghost"
                                    size="sm"
                                    onClick={() =>
                                        isDoubles
                                            ? onPairsChange(
                                                  pairs.filter(
                                                      (_, pairIndex) =>
                                                          pairIndex !== index,
                                                  ),
                                              )
                                            : onPlayersChange(
                                                  players.filter(
                                                      (player) =>
                                                          player.key !==
                                                          entry.key,
                                                  ),
                                              )
                                    }
                                >
                                    {isDoubles
                                        ? t('tournament_remove_pair')
                                        : t('tournament_remove_player')}
                                </Button>
                                <Button
                                    type="button"
                                    variant="ghost"
                                    size="icon"
                                    onClick={() => moveEntry(index, -1)}
                                    disabled={index === 0}
                                    aria-label={t('tournament_move_up')}
                                >
                                    <ArrowUp className="size-4" />
                                </Button>
                                <Button
                                    type="button"
                                    variant="ghost"
                                    size="icon"
                                    onClick={() => moveEntry(index, 1)}
                                    disabled={index === entries.length - 1}
                                    aria-label={t('tournament_move_down')}
                                >
                                    <ArrowDown className="size-4" />
                                </Button>
                            </div>
                        ))}
                    </div>
                </div>
            )}
        </div>
    );
}
