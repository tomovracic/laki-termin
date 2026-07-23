import { Head, router } from '@inertiajs/react';
import type { FormEvent } from 'react';
import { useMemo, useState } from 'react';
import { StatusBanner } from '@/components/admin/status-banner';
import InputError from '@/components/input-error';
import { KnockoutCreateWizard } from '@/components/league/knockout-create-wizard';
import type { KnockoutDrawMode, LeagueFormat, LeagueSummary, LeagueUserOption } from '@/components/league/types';
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
    DialogTrigger,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
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

type LeaguesPageProps = {
    leagues: LeagueSummary[];
    users?: LeagueUserOption[];
    can_manage?: boolean;
};

type ApiErrorResponse = {
    message?: string;
    errors?: Record<string, string[]>;
};

function roundsLabel(rounds: number, t: (key: string) => string): string {
    if (rounds === 1) {
        return t('league_rounds_once');
    }

    if (rounds === 2) {
        return t('league_rounds_twice');
    }

    if (rounds === 3) {
        return t('league_rounds_thrice');
    }

    return t('league_rounds_count').replace('{count}', `${rounds}`);
}

export default function LeaguesPage({
    leagues,
    users = [],
    can_manage: canManage = false,
}: LeaguesPageProps) {
    const { t } = useI18n();
    const [format, setFormat] = useState<LeagueFormat>('round_robin');
    const [name, setName] = useState('');
    const [rounds, setRounds] = useState('1');
    const [setsBestOf, setSetsBestOf] = useState('3');
    const [knockoutDrawMode, setKnockoutDrawMode] = useState<KnockoutDrawMode>('seeded');
    const [selectedParticipantIds, setSelectedParticipantIds] = useState<number[]>([]);
    const [knockoutParticipantIds, setKnockoutParticipantIds] = useState<number[]>([]);
    const [isCreateModalOpen, setIsCreateModalOpen] = useState(false);
    const [isCreating, setIsCreating] = useState(false);
    const [deleteTarget, setDeleteTarget] = useState<LeagueSummary | null>(null);
    const [isDeleting, setIsDeleting] = useState(false);
    const [errors, setErrors] = useState<Record<string, string[]>>({});
    const [message, setMessage] = useState<string | null>(null);
    const [errorMessage, setErrorMessage] = useState<string | null>(null);

    const breadcrumbs: BreadcrumbItem[] = [
        { title: t('dashboard'), href: dashboard() },
        { title: t('leagues'), href: '/dashboard/leagues' },
    ];

    const participantToggleLabel = useMemo(
        () => (user: LeagueUserOption) => `${user.name} (${user.email})`,
        [],
    );

    function toggleParticipant(userId: number) {
        setSelectedParticipantIds((current) =>
            current.includes(userId)
                ? current.filter((id) => id !== userId)
                : [...current, userId],
        );
    }

    function resetForm() {
        setFormat('round_robin');
        setName('');
        setRounds('1');
        setSetsBestOf('3');
        setKnockoutDrawMode('seeded');
        setSelectedParticipantIds([]);
        setKnockoutParticipantIds([]);
        setErrors({});
    }

    async function handleCreateLeague(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();
        setIsCreating(true);
        setErrors({});
        setErrorMessage(null);
        setMessage(null);

        const body =
            format === 'knockout'
                ? {
                      name,
                      format: 'knockout',
                      sets_best_of: Number.parseInt(setsBestOf, 10),
                      knockout_draw_mode: knockoutDrawMode,
                      participant_ids: knockoutParticipantIds,
                  }
                : {
                      name,
                      format: 'round_robin',
                      rounds: Number.parseInt(rounds, 10),
                      participant_ids: selectedParticipantIds,
                  };

        try {
            const response = await fetch('/leagues', {
                method: 'POST',
                headers: {
                    Accept: 'application/json',
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    ...csrfHeaders(),
                },
                body: JSON.stringify(body),
            });

            const payload = (await response.json()) as ApiErrorResponse & { data?: LeagueSummary };

            if (!response.ok) {
                if (payload.errors) {
                    setErrors(payload.errors);
                }

                setErrorMessage(payload.message ?? t('league_unable_create'));
                return;
            }

            setMessage(format === 'knockout' ? t('tournament_created') : t('league_created'));
            setIsCreateModalOpen(false);
            resetForm();
            router.reload({ only: ['leagues'] });
        } catch {
            setErrorMessage(t('league_unable_create'));
        } finally {
            setIsCreating(false);
        }
    }

    async function handleDeleteLeague(league: LeagueSummary) {
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

            const payload = (await response.json()) as ApiErrorResponse;

            if (!response.ok) {
                setErrorMessage(payload.message ?? t('league_unable_delete'));
                return;
            }

            setDeleteTarget(null);
            setMessage(t('league_deleted'));
            router.reload({ only: ['leagues'] });
        } catch {
            setErrorMessage(t('league_unable_delete'));
        } finally {
            setIsDeleting(false);
        }
    }

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={t('leagues')} />
            <div className="flex h-full flex-1 flex-col gap-4 overflow-x-auto p-4">
                <StatusBanner message={message} error={errorMessage} />

                <div className="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <h1 className="text-2xl font-semibold tracking-tight">{t('leagues')}</h1>
                        <p className="mt-1 text-sm text-muted-foreground">
                            {canManage
                                ? t('leagues_admin_overview_description')
                                : t('leagues_user_description')}
                        </p>
                    </div>

                    {canManage && (
                        <Dialog
                            open={isCreateModalOpen}
                            onOpenChange={(open) => {
                                setIsCreateModalOpen(open);
                                if (!open) {
                                    resetForm();
                                }
                            }}
                        >
                            <DialogTrigger asChild>
                                <Button>{t('league_create')}</Button>
                            </DialogTrigger>
                            <DialogContent className="max-h-[90vh] overflow-y-auto sm:max-w-2xl">
                                <DialogHeader>
                                    <DialogTitle>{t('league_create')}</DialogTitle>
                                    <DialogDescription>
                                        {t('league_create_description')}
                                    </DialogDescription>
                                </DialogHeader>
                                <form onSubmit={handleCreateLeague} className="space-y-4">
                                    <div className="space-y-2">
                                        <Label>{t('tournament_format')}</Label>
                                        <Select
                                            value={format}
                                            onValueChange={(value) =>
                                                setFormat(value as LeagueFormat)
                                            }
                                        >
                                            <SelectTrigger>
                                                <SelectValue />
                                            </SelectTrigger>
                                            <SelectContent>
                                                <SelectItem value="round_robin">
                                                    {t('tournament_format_round_robin')}
                                                </SelectItem>
                                                <SelectItem value="knockout">
                                                    {t('tournament_format_knockout')}
                                                </SelectItem>
                                            </SelectContent>
                                        </Select>
                                    </div>

                                    {format === 'knockout' ? (
                                        <KnockoutCreateWizard
                                            name={name}
                                            onNameChange={setName}
                                            setsBestOf={setsBestOf}
                                            onSetsBestOfChange={setSetsBestOf}
                                            drawMode={knockoutDrawMode}
                                            onDrawModeChange={setKnockoutDrawMode}
                                            users={users}
                                            participantIds={knockoutParticipantIds}
                                            onParticipantIdsChange={setKnockoutParticipantIds}
                                            errors={errors}
                                        />
                                    ) : (
                                        <>
                                            <div className="space-y-2">
                                                <Label htmlFor="league-name">{t('name')}</Label>
                                                <Input
                                                    id="league-name"
                                                    value={name}
                                                    onChange={(event) => setName(event.target.value)}
                                                    required
                                                />
                                                <InputError message={errors.name?.[0]} />
                                            </div>

                                            <div className="space-y-2">
                                                <Label>{t('league_rounds')}</Label>
                                                <Select value={rounds} onValueChange={setRounds}>
                                                    <SelectTrigger>
                                                        <SelectValue />
                                                    </SelectTrigger>
                                                    <SelectContent>
                                                        {[1, 2, 3, 4, 5].map((value) => (
                                                            <SelectItem key={value} value={`${value}`}>
                                                                {roundsLabel(value, t)}
                                                            </SelectItem>
                                                        ))}
                                                    </SelectContent>
                                                </Select>
                                                <InputError message={errors.rounds?.[0]} />
                                            </div>

                                            <div className="space-y-2">
                                                <Label>{t('league_participants')}</Label>
                                                <div className="max-h-48 space-y-2 overflow-y-auto rounded-md border p-3">
                                                    {users.map((user) => (
                                                        <label
                                                            key={user.id}
                                                            className="flex items-center gap-2 text-sm"
                                                        >
                                                            <input
                                                                type="checkbox"
                                                                checked={selectedParticipantIds.includes(
                                                                    user.id,
                                                                )}
                                                                onChange={() =>
                                                                    toggleParticipant(user.id)
                                                                }
                                                            />
                                                            <span>{participantToggleLabel(user)}</span>
                                                        </label>
                                                    ))}
                                                </div>
                                                <InputError message={errors.participant_ids?.[0]} />
                                            </div>
                                        </>
                                    )}

                                    <div className="flex justify-end gap-2">
                                        <Button
                                            type="button"
                                            variant="outline"
                                            onClick={() => setIsCreateModalOpen(false)}
                                            disabled={isCreating}
                                        >
                                            {t('cancel')}
                                        </Button>
                                        <Button
                                            type="submit"
                                            disabled={
                                                isCreating ||
                                                (format === 'knockout' &&
                                                    knockoutParticipantIds.length < 2)
                                            }
                                        >
                                            {isCreating ? t('creating') : t('league_create')}
                                        </Button>
                                    </div>
                                </form>
                            </DialogContent>
                        </Dialog>
                    )}
                </div>

                <div className="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
                    {leagues.map((league) => (
                        <Card key={league.id}>
                            <CardHeader>
                                <CardTitle className="text-lg">{league.name}</CardTitle>
                            </CardHeader>
                            <CardContent className="space-y-3">
                                <div className="flex flex-wrap gap-2">
                                    <Badge variant="outline">
                                        {league.format === 'knockout'
                                            ? t('tournament_format_knockout')
                                            : roundsLabel(league.rounds, t)}
                                    </Badge>
                                    {league.format === 'knockout' && league.sets_best_of ? (
                                        <Badge variant="outline">
                                            {t('tournament_best_of').replace(
                                                '{count}',
                                                `${league.sets_best_of}`,
                                            )}
                                        </Badge>
                                    ) : null}
                                    <Badge variant="secondary">
                                        {league.participants_count}{' '}
                                        {t('league_participants').toLowerCase()}
                                    </Badge>
                                </div>
                                <p className="text-sm text-muted-foreground">
                                    {league.played_matches_count}/{league.matches_count}{' '}
                                    {t('league_matches_played').toLowerCase()}
                                </p>
                                <div className="flex gap-2">
                                    <Button
                                        variant="outline"
                                        className="flex-1"
                                        onClick={() =>
                                            router.visit(`/dashboard/leagues/${league.id}`)
                                        }
                                    >
                                        {canManage ? t('league_manage') : t('league_view')}
                                    </Button>
                                    {canManage && (
                                        <Button
                                            variant="destructive"
                                            onClick={() => setDeleteTarget(league)}
                                        >
                                            {t('league_delete')}
                                        </Button>
                                    )}
                                </div>
                            </CardContent>
                        </Card>
                    ))}
                </div>

                {leagues.length === 0 && (
                    <p className="text-sm text-muted-foreground">{t('league_no_leagues')}</p>
                )}

                {canManage && (
                    <Dialog
                        open={deleteTarget !== null}
                        onOpenChange={(open) => {
                            if (!open) {
                                setDeleteTarget(null);
                            }
                        }}
                    >
                        <DialogContent>
                            <DialogHeader>
                                <DialogTitle>{t('league_delete')}</DialogTitle>
                                <DialogDescription>{t('league_confirm_delete')}</DialogDescription>
                            </DialogHeader>
                            <DialogFooter>
                                <Button
                                    type="button"
                                    variant="outline"
                                    onClick={() => setDeleteTarget(null)}
                                    disabled={isDeleting}
                                >
                                    {t('cancel')}
                                </Button>
                                <Button
                                    type="button"
                                    variant="destructive"
                                    disabled={deleteTarget === null || isDeleting}
                                    onClick={() => {
                                        if (deleteTarget === null) {
                                            return;
                                        }

                                        void handleDeleteLeague(deleteTarget);
                                    }}
                                >
                                    {isDeleting ? t('saving') : t('league_delete')}
                                </Button>
                            </DialogFooter>
                        </DialogContent>
                    </Dialog>
                )}
            </div>
        </AppLayout>
    );
}
