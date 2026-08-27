import { Head, router, usePage } from '@inertiajs/react';
import { useState } from 'react';
import { LeagueMatchResultForm } from '@/components/admin/league-match-result-form';
import { StatusBanner } from '@/components/admin/status-banner';
import { GroupStageSection } from '@/components/league/group-stage-section';
import { LeagueMatchesSection } from '@/components/league/league-matches-section';
import { LeagueStandingsTable } from '@/components/league/league-standings-table';
import { TournamentBracket } from '@/components/league/tournament-bracket';
import type {
    KnockoutChampion,
    LeagueDetail,
    LeagueGroupSummary,
    LeagueMatch,
    LeagueMatchResultPayload,
    LeagueParticipant,
    LeagueStandingsEntry,
    LeagueUserOption,
} from '@/components/league/types';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import AppLayout from '@/layouts/app-layout';
import { csrfHeaders } from '@/lib/csrf';
import { useI18n } from '@/lib/i18n';
import { dashboard } from '@/routes';
import type { BreadcrumbItem } from '@/types';
import type { Auth } from '@/types/auth';

type LeagueShowPageProps = {
    league: LeagueDetail;
    standings: LeagueStandingsEntry[];
    matches: LeagueMatch[];
    participants: LeagueParticipant[];
    available_users: LeagueUserOption[];
    groups?: LeagueGroupSummary[];
    qualifiers?: LeagueStandingsEntry[];
    knockout_champion?: KnockoutChampion | null;
    can_manage?: boolean;
};

type ApiErrorResponse = {
    message?: string;
    errors?: Record<string, string[]>;
};

export default function LeagueShowPage({
    league: initialLeague,
    standings: initialStandings,
    matches: initialMatches,
    participants: initialParticipants,
    available_users: initialAvailableUsers,
    groups: initialGroups = [],
    qualifiers: initialQualifiers = [],
    knockout_champion: initialChampion = null,
    can_manage: canManage = false,
}: LeagueShowPageProps) {
    const { t } = useI18n();
    const { auth } = usePage<{ auth: Auth }>().props;
    const currentUserId = auth.user?.id ?? null;
    const [league, setLeague] = useState(initialLeague);
    const [standings, setStandings] = useState(initialStandings);
    const [matches, setMatches] = useState(initialMatches);
    const [participants, setParticipants] = useState(initialParticipants);
    const [availableUsers, setAvailableUsers] = useState(initialAvailableUsers);
    const [groups, setGroups] = useState(initialGroups);
    const [qualifiers, setQualifiers] = useState(initialQualifiers);
    const [knockoutChampion, setKnockoutChampion] = useState(initialChampion);
    const [selectedUserId, setSelectedUserId] = useState<string>('');
    const [isAddingParticipant, setIsAddingParticipant] = useState(false);
    const [selectedMatch, setSelectedMatch] = useState<LeagueMatch | null>(
        null,
    );
    const [isSubmittingResult, setIsSubmittingResult] = useState(false);
    const [resultErrors, setResultErrors] = useState<string[]>([]);
    const [isDeleteDialogOpen, setIsDeleteDialogOpen] = useState(false);
    const [isDeleting, setIsDeleting] = useState(false);
    const [isFinishingRound, setIsFinishingRound] = useState(false);
    const [isStartingKnockout, setIsStartingKnockout] = useState(false);
    const [message, setMessage] = useState<string | null>(null);
    const [errorMessage, setErrorMessage] = useState<string | null>(null);

    const isKnockout = league.format === 'knockout';
    const isGroupKnockout = league.format === 'group_knockout';
    const isTournament = isKnockout || isGroupKnockout;
    const isKnockoutStage = isKnockout || league.current_stage === 'knockout';
    const isGroupStage = isGroupKnockout && league.current_stage !== 'knockout';
    const groupMatches = matches.filter(
        (match) => match.league_group_id != null,
    );
    const knockoutMatches = matches.filter(
        (match) => match.bracket_round != null,
    );
    const isDoubles = league.participant_mode === 'doubles';
    const bestOf = (
        league.sets_best_of === 1 || league.sets_best_of === 5
            ? league.sets_best_of
            : 3
    ) as 1 | 3 | 5;
    const isParticipant =
        standings.some((entry) => entry.user_id === currentUserId) ||
        participants.some(
            (participant) =>
                participant.user_id === currentUserId ||
                participant.partner_user_id === currentUserId,
        );

    const breadcrumbs: BreadcrumbItem[] = [
        { title: t('dashboard'), href: dashboard() },
        { title: t('leagues'), href: '/dashboard/leagues' },
        { title: league.name, href: `/dashboard/leagues/${league.id}` },
    ];

    async function reloadLeagueData() {
        router.reload({
            only: [
                'league',
                'standings',
                'matches',
                'participants',
                'available_users',
                'groups',
                'qualifiers',
                'knockout_champion',
                'can_manage',
            ],
            onSuccess: (visit) => {
                const props = visit.props as LeagueShowPageProps;
                setLeague(props.league);
                setStandings(props.standings);
                setMatches(props.matches);
                setParticipants(props.participants);
                setAvailableUsers(props.available_users);
                setGroups(props.groups ?? []);
                setQualifiers(props.qualifiers ?? []);
                setKnockoutChampion(props.knockout_champion ?? null);
            },
        });
    }

    async function handleAddParticipant() {
        if (selectedUserId === '') {
            return;
        }

        setIsAddingParticipant(true);
        setMessage(null);
        setErrorMessage(null);

        try {
            const response = await fetch(`/leagues/${league.id}/participants`, {
                method: 'POST',
                headers: {
                    Accept: 'application/json',
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    ...csrfHeaders(),
                },
                body: JSON.stringify({
                    user_id: Number.parseInt(selectedUserId, 10),
                }),
            });

            const payload = (await response.json()) as ApiErrorResponse;

            if (!response.ok) {
                setErrorMessage(
                    payload.errors?.user_id?.[0] ??
                        payload.message ??
                        t('league_unable_add_participant'),
                );
                return;
            }

            setMessage(t('league_participant_added'));
            setSelectedUserId('');
            await reloadLeagueData();
        } catch {
            setErrorMessage(t('league_unable_add_participant'));
        } finally {
            setIsAddingParticipant(false);
        }
    }

    async function handleSubmitResult(payload: LeagueMatchResultPayload) {
        if (selectedMatch === null) {
            return;
        }

        setIsSubmittingResult(true);
        setResultErrors([]);
        setMessage(null);
        setErrorMessage(null);

        try {
            const response = await fetch(
                `/leagues/${league.id}/matches/${selectedMatch.id}/result`,
                {
                    method: 'PATCH',
                    headers: {
                        Accept: 'application/json',
                        'Content-Type': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                        ...csrfHeaders(),
                    },
                    body: JSON.stringify(payload),
                },
            );

            const body = (await response.json()) as ApiErrorResponse;

            if (!response.ok) {
                setResultErrors(
                    body.errors?.result ?? [
                        body.message ?? t('league_unable_save_result'),
                    ],
                );
                return;
            }

            setMessage(t('league_result_saved'));
            setSelectedMatch(null);
            await reloadLeagueData();
        } catch {
            setResultErrors([t('league_unable_save_result')]);
        } finally {
            setIsSubmittingResult(false);
        }
    }

    async function handleFinishRound() {
        setIsFinishingRound(true);
        setMessage(null);
        setErrorMessage(null);

        try {
            const response = await fetch(
                `/leagues/${league.id}/rounds/finish`,
                {
                    method: 'POST',
                    headers: {
                        Accept: 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                        ...csrfHeaders(),
                    },
                },
            );

            const body = (await response.json()) as ApiErrorResponse;

            if (!response.ok) {
                setErrorMessage(
                    body.errors?.round?.[0] ??
                        body.message ??
                        t('tournament_unable_finish_round'),
                );
                return;
            }

            setMessage(t('tournament_round_finished'));
            await reloadLeagueData();
        } catch {
            setErrorMessage(t('tournament_unable_finish_round'));
        } finally {
            setIsFinishingRound(false);
        }
    }

    async function handleStartKnockout() {
        setIsStartingKnockout(true);
        setMessage(null);
        setErrorMessage(null);

        try {
            const response = await fetch(
                `/leagues/${league.id}/knockout/start`,
                {
                    method: 'POST',
                    headers: {
                        Accept: 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                        ...csrfHeaders(),
                    },
                },
            );

            const body = (await response.json()) as ApiErrorResponse;

            if (!response.ok) {
                setErrorMessage(
                    body.errors?.stage?.[0] ??
                        body.message ??
                        t('tournament_unable_start_knockout'),
                );
                return;
            }

            setMessage(t('tournament_knockout_started'));
            await reloadLeagueData();
        } catch {
            setErrorMessage(t('tournament_unable_start_knockout'));
        } finally {
            setIsStartingKnockout(false);
        }
    }

    async function handleDeleteLeague() {
        setIsDeleting(true);
        setMessage(null);
        setErrorMessage(null);

        try {
            const response = await fetch(`/leagues/${league.id}`, {
                method: 'DELETE',
                headers: {
                    Accept: 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    ...csrfHeaders(),
                },
            });

            const body = (await response.json()) as ApiErrorResponse;

            if (!response.ok) {
                setErrorMessage(body.message ?? t('league_unable_delete'));
                setIsDeleteDialogOpen(false);
                return;
            }

            router.visit('/dashboard/leagues');
        } catch {
            setErrorMessage(t('league_unable_delete'));
            setIsDeleteDialogOpen(false);
        } finally {
            setIsDeleting(false);
        }
    }

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={league.name} />
            <div className="flex h-full flex-1 flex-col gap-4 overflow-x-auto p-4">
                <StatusBanner message={message} error={errorMessage} />

                <div className="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <h1 className="text-2xl font-semibold tracking-tight">
                            {league.name}
                        </h1>
                        <div className="mt-2 flex flex-wrap gap-2">
                            <Badge variant="outline">
                                {isGroupKnockout
                                    ? t('tournament_format_group_knockout')
                                    : isKnockout
                                      ? t('tournament_format_knockout')
                                      : `${league.participants_count} ${t('league_participants').toLowerCase()}`}
                            </Badge>
                            {isTournament && (
                                <Badge variant="outline">
                                    {t('tournament_best_of').replace(
                                        '{count}',
                                        `${bestOf}`,
                                    )}
                                </Badge>
                            )}
                            {isGroupKnockout &&
                                league.current_stage === 'group' && (
                                    <Badge variant="outline">
                                        {t('tournament_stage_group')}
                                    </Badge>
                                )}
                            {isGroupKnockout &&
                                league.current_stage === 'knockout' && (
                                    <Badge variant="outline">
                                        {t('tournament_stage_knockout')}
                                    </Badge>
                                )}
                            {isDoubles && (
                                <Badge variant="outline">
                                    {t('tournament_participant_mode_doubles')}
                                </Badge>
                            )}
                            {canManage &&
                                isKnockoutStage &&
                                league.knockout_draw_mode === 'random' && (
                                    <Badge variant="outline">
                                        {t('tournament_draw_random')}
                                    </Badge>
                                )}
                            {canManage &&
                                isKnockoutStage &&
                                league.knockout_draw_mode === 'seeded' && (
                                    <Badge variant="outline">
                                        {t('tournament_draw_seeded')}
                                    </Badge>
                                )}
                            {knockoutChampion && (
                                <Badge variant="default">
                                    {t('tournament_champion')}:{' '}
                                    {knockoutChampion.name}
                                </Badge>
                            )}
                            <Badge variant="secondary">
                                {league.played_matches_count}/
                                {league.matches_count}{' '}
                                {t('league_matches_played').toLowerCase()}
                            </Badge>
                        </div>
                    </div>
                    {canManage && (
                        <Button
                            variant="destructive"
                            onClick={() => setIsDeleteDialogOpen(true)}
                        >
                            {t('league_delete')}
                        </Button>
                    )}
                </div>

                {isGroupKnockout && (
                    <GroupStageSection
                        groups={groups}
                        qualifiers={qualifiers}
                        highlightUserId={canManage ? null : currentUserId}
                        qualifyPerGroup={league.qualify_per_group}
                        bestRunnersUp={league.best_runners_up}
                        matches={groupMatches}
                        currentUserId={currentUserId}
                        showMatches={!isKnockoutStage}
                        onEnterResult={
                            canManage
                                ? (match) => {
                                      setResultErrors([]);
                                      setSelectedMatch(match);
                                  }
                                : undefined
                        }
                    />
                )}

                {canManage &&
                    isGroupStage &&
                    Boolean(league.can_start_knockout) && (
                        <Card>
                            <CardHeader>
                                <CardTitle>
                                    {t('tournament_start_knockout')}
                                </CardTitle>
                            </CardHeader>
                            <CardContent className="flex flex-wrap items-center justify-between gap-3">
                                <p className="text-sm text-muted-foreground">
                                    {t('tournament_start_knockout_hint')}
                                </p>
                                <Button
                                    onClick={() => {
                                        void handleStartKnockout();
                                    }}
                                    disabled={isStartingKnockout}
                                >
                                    {isStartingKnockout
                                        ? t('saving')
                                        : t('tournament_start_knockout')}
                                </Button>
                            </CardContent>
                        </Card>
                    )}

                {isKnockoutStage ? (
                    <Card>
                        <CardHeader>
                            <CardTitle>{t('tournament_bracket')}</CardTitle>
                        </CardHeader>
                        <CardContent>
                            <TournamentBracket
                                matches={
                                    knockoutMatches.length > 0
                                        ? knockoutMatches
                                        : matches
                                }
                                canEnterResults={canManage}
                                canFinishRound={
                                    canManage &&
                                    Boolean(league.can_finish_round)
                                }
                                isFinishingRound={isFinishingRound}
                                currentUserId={currentUserId}
                                currentBracketRound={
                                    league.current_bracket_round ?? null
                                }
                                nextRoundPending={Boolean(
                                    league.next_round_pending,
                                )}
                                championName={knockoutChampion?.name ?? null}
                                onFinishRound={
                                    canManage
                                        ? () => {
                                              void handleFinishRound();
                                          }
                                        : undefined
                                }
                                onEnterResult={
                                    canManage
                                        ? (match) => {
                                              setResultErrors([]);
                                              setSelectedMatch(match);
                                          }
                                        : undefined
                                }
                            />
                        </CardContent>
                    </Card>
                ) : !isGroupKnockout ? (
                    <Card>
                        <CardHeader>
                            <CardTitle>{t('league_standings')}</CardTitle>
                        </CardHeader>
                        <CardContent>
                            <LeagueStandingsTable
                                standings={standings}
                                highlightUserId={
                                    canManage ? null : currentUserId
                                }
                            />
                        </CardContent>
                    </Card>
                ) : null}

                {canManage && !isGroupKnockout && (
                    <Card>
                        <CardHeader>
                            <CardTitle>{t('league_participants')}</CardTitle>
                        </CardHeader>
                        <CardContent className="space-y-4">
                            <div className="flex flex-wrap gap-2">
                                {participants.map((participant) => (
                                    <Badge
                                        key={participant.id}
                                        variant="outline"
                                    >
                                        {participant.seed
                                            ? `${participant.seed}. `
                                            : ''}
                                        {participant.name}
                                    </Badge>
                                ))}
                            </div>

                            {!isTournament && availableUsers.length > 0 && (
                                <div className="flex flex-col gap-2 sm:flex-row sm:items-end">
                                    <div className="flex-1 space-y-2">
                                        <Select
                                            value={selectedUserId}
                                            onValueChange={setSelectedUserId}
                                        >
                                            <SelectTrigger>
                                                <SelectValue
                                                    placeholder={t(
                                                        'league_add_participant',
                                                    )}
                                                />
                                            </SelectTrigger>
                                            <SelectContent>
                                                {availableUsers.map((user) => (
                                                    <SelectItem
                                                        key={user.id}
                                                        value={`${user.id}`}
                                                    >
                                                        {user.name} (
                                                        {user.email})
                                                    </SelectItem>
                                                ))}
                                            </SelectContent>
                                        </Select>
                                    </div>
                                    <Button
                                        onClick={handleAddParticipant}
                                        disabled={
                                            isAddingParticipant ||
                                            selectedUserId === ''
                                        }
                                    >
                                        {isAddingParticipant
                                            ? t('saving')
                                            : t('league_add_participant')}
                                    </Button>
                                </div>
                            )}
                        </CardContent>
                    </Card>
                )}

                {!isKnockoutStage && !isGroupKnockout && (
                    <LeagueMatchesSection
                        matches={matches}
                        standings={standings}
                        currentUserId={currentUserId}
                        isParticipant={isParticipant}
                        onEnterResult={
                            canManage
                                ? (match) => {
                                      setResultErrors([]);
                                      setSelectedMatch(match);
                                  }
                                : undefined
                        }
                    />
                )}

                {canManage && (
                    <>
                        <Dialog
                            open={selectedMatch !== null}
                            onOpenChange={(open) =>
                                !open && setSelectedMatch(null)
                            }
                        >
                            <DialogContent className="max-h-[90vh] overflow-y-auto sm:max-w-2xl">
                                <DialogHeader>
                                    <DialogTitle>
                                        {selectedMatch?.status === 'played'
                                            ? t('league_edit_result')
                                            : t('league_enter_result')}
                                    </DialogTitle>
                                </DialogHeader>
                                {selectedMatch !== null && (
                                    <LeagueMatchResultForm
                                        match={selectedMatch}
                                        bestOf={bestOf}
                                        onSubmit={handleSubmitResult}
                                        onCancel={() => setSelectedMatch(null)}
                                        isSubmitting={isSubmittingResult}
                                        errors={resultErrors}
                                    />
                                )}
                            </DialogContent>
                        </Dialog>

                        <Dialog
                            open={isDeleteDialogOpen}
                            onOpenChange={setIsDeleteDialogOpen}
                        >
                            <DialogContent>
                                <DialogHeader>
                                    <DialogTitle>
                                        {t('league_delete')}
                                    </DialogTitle>
                                    <DialogDescription>
                                        {t('league_confirm_delete')}
                                    </DialogDescription>
                                </DialogHeader>
                                <DialogFooter>
                                    <Button
                                        type="button"
                                        variant="outline"
                                        onClick={() =>
                                            setIsDeleteDialogOpen(false)
                                        }
                                        disabled={isDeleting}
                                    >
                                        {t('cancel')}
                                    </Button>
                                    <Button
                                        type="button"
                                        variant="destructive"
                                        disabled={isDeleting}
                                        onClick={() => {
                                            void handleDeleteLeague();
                                        }}
                                    >
                                        {isDeleting
                                            ? t('saving')
                                            : t('league_delete')}
                                    </Button>
                                </DialogFooter>
                            </DialogContent>
                        </Dialog>
                    </>
                )}
            </div>
        </AppLayout>
    );
}
