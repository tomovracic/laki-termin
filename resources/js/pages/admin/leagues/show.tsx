import { Head, router, usePage } from '@inertiajs/react';
import { useState } from 'react';
import { LeagueMatchResultForm } from '@/components/admin/league-match-result-form';
import { AdminSectionLayout } from '@/components/admin/admin-section-layout';
import { StatusBanner } from '@/components/admin/status-banner';
import { LeagueMatchesSection } from '@/components/league/league-matches-section';
import { LeagueStandingsTable } from '@/components/league/league-standings-table';
import { TournamentBracket } from '@/components/league/tournament-bracket';
import type {
    LeagueDetail,
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
import { csrfHeaders } from '@/lib/csrf';
import { useI18n } from '@/lib/i18n';
import type { Auth } from '@/types/auth';

type AdminLeagueShowPageProps = {
    league: LeagueDetail;
    standings: LeagueStandingsEntry[];
    matches: LeagueMatch[];
    participants: LeagueParticipant[];
    available_users: LeagueUserOption[];
};

type ApiErrorResponse = {
    message?: string;
    errors?: Record<string, string[]>;
};

export default function AdminLeagueShowPage({
    league: initialLeague,
    standings: initialStandings,
    matches: initialMatches,
    participants: initialParticipants,
    available_users: initialAvailableUsers,
}: AdminLeagueShowPageProps) {
    const { t } = useI18n();
    const { auth } = usePage<{ auth: Auth }>().props;
    const currentUserId = auth.user?.id ?? null;
    const [league] = useState(initialLeague);
    const [standings, setStandings] = useState(initialStandings);
    const [matches, setMatches] = useState(initialMatches);
    const [participants, setParticipants] = useState(initialParticipants);
    const [availableUsers, setAvailableUsers] = useState(initialAvailableUsers);
    const [selectedUserId, setSelectedUserId] = useState<string>('');
    const [isAddingParticipant, setIsAddingParticipant] = useState(false);
    const [selectedMatch, setSelectedMatch] = useState<LeagueMatch | null>(null);
    const [isSubmittingResult, setIsSubmittingResult] = useState(false);
    const [resultErrors, setResultErrors] = useState<string[]>([]);
    const [message, setMessage] = useState<string | null>(null);
    const [errorMessage, setErrorMessage] = useState<string | null>(null);

    const isKnockout = league.format === 'knockout';
    const bestOf = (league.sets_best_of === 1 || league.sets_best_of === 5 ? league.sets_best_of : 3) as
        | 1
        | 3
        | 5;
    const isParticipant = standings.some((entry) => entry.user_id === currentUserId);

    async function reloadLeagueData() {
        router.reload({
            only: ['league', 'standings', 'matches', 'participants', 'available_users'],
            onSuccess: (visit) => {
                const props = visit.props as AdminLeagueShowPageProps;
                setStandings(props.standings);
                setMatches(props.matches);
                setParticipants(props.participants);
                setAvailableUsers(props.available_users);
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
                    body.errors?.result ?? [body.message ?? t('league_unable_save_result')],
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

    return (
        <AdminSectionLayout title={league.name} description={t('league_admin_show_description')}>
            <Head title={league.name} />
            <StatusBanner message={message} error={errorMessage} />

            <div className="flex flex-wrap gap-2">
                <Badge variant="outline">
                    {isKnockout
                        ? t('tournament_format_knockout')
                        : `${league.participants_count} ${t('league_participants').toLowerCase()}`}
                </Badge>
                {isKnockout && (
                    <Badge variant="outline">
                        {t('tournament_best_of').replace('{count}', `${bestOf}`)}
                    </Badge>
                )}
                <Badge variant="secondary">
                    {league.played_matches_count}/{league.matches_count}{' '}
                    {t('league_matches_played').toLowerCase()}
                </Badge>
            </div>

            {isKnockout ? (
                <Card>
                    <CardHeader>
                        <CardTitle>{t('tournament_bracket')}</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <TournamentBracket
                            matches={matches}
                            canEnterResults
                            currentUserId={currentUserId}
                            onEnterResult={(match) => {
                                setResultErrors([]);
                                setSelectedMatch(match);
                            }}
                        />
                    </CardContent>
                </Card>
            ) : (
                <Card>
                    <CardHeader>
                        <CardTitle>{t('league_standings')}</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <LeagueStandingsTable standings={standings} />
                    </CardContent>
                </Card>
            )}

            <Card>
                <CardHeader>
                    <CardTitle>{t('league_participants')}</CardTitle>
                </CardHeader>
                <CardContent className="space-y-4">
                    <div className="flex flex-wrap gap-2">
                        {participants.map((participant) => (
                            <Badge key={participant.id} variant="outline">
                                {participant.seed ? `${participant.seed}. ` : ''}
                                {participant.name}
                            </Badge>
                        ))}
                    </div>

                    {!isKnockout && availableUsers.length > 0 && (
                        <div className="flex flex-col gap-2 sm:flex-row sm:items-end">
                            <div className="flex-1 space-y-2">
                                <Select value={selectedUserId} onValueChange={setSelectedUserId}>
                                    <SelectTrigger>
                                        <SelectValue placeholder={t('league_add_participant')} />
                                    </SelectTrigger>
                                    <SelectContent>
                                        {availableUsers.map((user) => (
                                            <SelectItem key={user.id} value={`${user.id}`}>
                                                {user.name} ({user.email})
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                            </div>
                            <Button
                                onClick={handleAddParticipant}
                                disabled={isAddingParticipant || selectedUserId === ''}
                            >
                                {isAddingParticipant ? t('saving') : t('league_add_participant')}
                            </Button>
                        </div>
                    )}
                </CardContent>
            </Card>

            {!isKnockout && (
                <LeagueMatchesSection
                    matches={matches}
                    standings={standings}
                    currentUserId={currentUserId}
                    isParticipant={isParticipant}
                    onEnterResult={(match) => {
                        setResultErrors([]);
                        setSelectedMatch(match);
                    }}
                />
            )}

            <Dialog
                open={selectedMatch !== null}
                onOpenChange={(open) => !open && setSelectedMatch(null)}
            >
                <DialogContent className="max-h-[90vh] overflow-y-auto sm:max-w-2xl">
                    <DialogHeader>
                        <DialogTitle>{t('league_enter_result')}</DialogTitle>
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
        </AdminSectionLayout>
    );
}
