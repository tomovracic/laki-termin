import { Head, router, usePage } from '@inertiajs/react';
import { useState } from 'react';
import { StatusBanner } from '@/components/admin/status-banner';
import { CreatePlayedMatchForm } from '@/components/match-history/create-played-match-form';
import { EditPlayedMatchForm } from '@/components/match-history/edit-played-match-form';
import { MatchHistorySection } from '@/components/match-history/match-history-section';
import type {
    CreatePlayedMatchPayload,
    MatchHistoryEntry,
    UpdatePlayedMatchPayload,
} from '@/components/match-history/types';
import { casualMatchIdToNumericId, leagueMatchIdToNumericId } from '@/components/match-history/types';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import AppLayout from '@/layouts/app-layout';
import { csrfHeaders } from '@/lib/csrf';
import { useI18n } from '@/lib/i18n';
import { dashboard } from '@/routes';
import type { Auth } from '@/types/auth';
import type { BreadcrumbItem } from '@/types';

type MatchHistoryPageProps = {
    matches: MatchHistoryEntry[];
};

type ApiErrorResponse = {
    message?: string;
    errors?: Record<string, string[]>;
};

export default function MatchHistoryPage({ matches: initialMatches }: MatchHistoryPageProps) {
    const { t } = useI18n();
    const { auth } = usePage<{ auth: Auth }>().props;
    const currentUser = auth.user;
    const [matches, setMatches] = useState(initialMatches);
    const [isCreateDialogOpen, setIsCreateDialogOpen] = useState(false);
    const [editingMatch, setEditingMatch] = useState<MatchHistoryEntry | null>(null);
    const [deleteTarget, setDeleteTarget] = useState<MatchHistoryEntry | null>(null);
    const [isSubmitting, setIsSubmitting] = useState(false);
    const [deletingMatchId, setDeletingMatchId] = useState<string | null>(null);
    const [formErrors, setFormErrors] = useState<Record<string, string[]>>({});
    const [resultErrors, setResultErrors] = useState<string[]>([]);
    const [message, setMessage] = useState<string | null>(null);
    const [errorMessage, setErrorMessage] = useState<string | null>(null);

    const breadcrumbs: BreadcrumbItem[] = [
        { title: t('dashboard'), href: dashboard() },
        { title: t('match_history'), href: '/dashboard/match-history' },
    ];

    function reloadMatches(successMessage?: string) {
        if (successMessage) {
            setMessage(successMessage);
        }

        router.reload({
            only: ['matches'],
            onSuccess: (visit) => {
                const props = visit.props as MatchHistoryPageProps;
                setMatches(props.matches);
            },
        });
    }

    async function handleCreateMatch(payload: CreatePlayedMatchPayload) {
        setIsSubmitting(true);
        setFormErrors({});
        setResultErrors([]);
        setMessage(null);
        setErrorMessage(null);

        try {
            const response = await fetch('/played-matches', {
                method: 'POST',
                headers: {
                    Accept: 'application/json',
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    ...csrfHeaders(),
                },
                body: JSON.stringify(payload),
            });

            const body = (await response.json()) as ApiErrorResponse;

            if (!response.ok) {
                setFormErrors(body.errors ?? {});
                setResultErrors(body.errors?.result ?? []);
                setErrorMessage(body.message ?? t('match_history_unable_save'));
                return;
            }

            setIsCreateDialogOpen(false);
            reloadMatches(t('match_history_saved'));
        } catch {
            setErrorMessage(t('match_history_unable_save'));
        } finally {
            setIsSubmitting(false);
        }
    }

    async function handleUpdateMatch(payload: UpdatePlayedMatchPayload) {
        if (editingMatch === null) {
            return;
        }

        setIsSubmitting(true);
        setFormErrors({});
        setResultErrors([]);
        setMessage(null);
        setErrorMessage(null);

        const updateUrl =
            editingMatch.source === 'league' && editingMatch.league !== null
                ? `/leagues/${editingMatch.league.id}/matches/${leagueMatchIdToNumericId(editingMatch.id)}/result`
                : `/played-matches/${casualMatchIdToNumericId(editingMatch.id)}`;

        try {
            const response = await fetch(updateUrl, {
                method: 'PATCH',
                headers: {
                    Accept: 'application/json',
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    ...csrfHeaders(),
                },
                body: JSON.stringify(payload),
            });

            const body = (await response.json()) as ApiErrorResponse;

            if (!response.ok) {
                setFormErrors(body.errors ?? {});
                setResultErrors(body.errors?.result ?? []);
                setErrorMessage(body.message ?? t('match_history_unable_update'));
                return;
            }

            setEditingMatch(null);
            reloadMatches(t('match_history_updated'));
        } catch {
            setErrorMessage(t('match_history_unable_update'));
        } finally {
            setIsSubmitting(false);
        }
    }

    async function handleDeleteMatch(match: MatchHistoryEntry) {
        setDeletingMatchId(match.id);
        setMessage(null);
        setErrorMessage(null);

        try {
            const response = await fetch(`/played-matches/${casualMatchIdToNumericId(match.id)}`, {
                method: 'DELETE',
                headers: {
                    Accept: 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    ...csrfHeaders(),
                },
            });

            const body = (await response.json()) as ApiErrorResponse;

            if (!response.ok) {
                setErrorMessage(body.message ?? t('match_history_unable_delete'));
                return;
            }

            setDeleteTarget(null);
            reloadMatches(t('match_history_deleted'));
        } catch {
            setErrorMessage(t('match_history_unable_delete'));
        } finally {
            setDeletingMatchId(null);
        }
    }

    function openEditDialog(match: MatchHistoryEntry) {
        setFormErrors({});
        setResultErrors([]);
        setErrorMessage(null);
        setEditingMatch(match);
    }

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={t('match_history')} />
            <div className="flex h-full flex-1 flex-col gap-4 overflow-x-auto p-4">
                <div className="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <h1 className="text-2xl font-semibold tracking-tight">{t('match_history')}</h1>
                        <p className="text-sm text-muted-foreground">{t('match_history_description')}</p>
                    </div>

                    <Dialog open={isCreateDialogOpen} onOpenChange={setIsCreateDialogOpen}>
                        <DialogTrigger asChild>
                            <Button>{t('match_history_add')}</Button>
                        </DialogTrigger>
                        <DialogContent className="max-h-[90vh] overflow-y-auto sm:max-w-2xl">
                            <DialogHeader>
                                <DialogTitle>{t('match_history_add')}</DialogTitle>
                            </DialogHeader>
                            {currentUser && (
                                <CreatePlayedMatchForm
                                    currentUserName={`${currentUser.first_name} ${currentUser.last_name}`.trim()}
                                    onSubmit={handleCreateMatch}
                                    onCancel={() => setIsCreateDialogOpen(false)}
                                    isSubmitting={isSubmitting}
                                    errors={formErrors}
                                    resultErrors={resultErrors}
                                />
                            )}
                        </DialogContent>
                    </Dialog>
                </div>

                <StatusBanner message={message} errorMessage={errorMessage} />

                <MatchHistorySection
                    matches={matches}
                    currentUserId={currentUser?.id ?? null}
                    deletingMatchId={deletingMatchId}
                    onEdit={openEditDialog}
                    onDelete={setDeleteTarget}
                />
            </div>

            <Dialog
                open={editingMatch !== null}
                onOpenChange={(open) => {
                    if (!open) {
                        setEditingMatch(null);
                    }
                }}
            >
                <DialogContent className="max-h-[90vh] overflow-y-auto sm:max-w-2xl">
                    <DialogHeader>
                        <DialogTitle>{t('match_history_edit')}</DialogTitle>
                    </DialogHeader>
                    {editingMatch && (
                        <EditPlayedMatchForm
                            match={editingMatch}
                            onSubmit={handleUpdateMatch}
                            onCancel={() => setEditingMatch(null)}
                            isSubmitting={isSubmitting}
                            errors={formErrors}
                            resultErrors={resultErrors}
                        />
                    )}
                </DialogContent>
            </Dialog>

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
                        <DialogTitle>{t('match_history_delete')}</DialogTitle>
                        <DialogDescription>{t('match_history_confirm_delete')}</DialogDescription>
                    </DialogHeader>
                    <DialogFooter>
                        <Button type="button" variant="outline" onClick={() => setDeleteTarget(null)}>
                            {t('cancel')}
                        </Button>
                        <Button
                            type="button"
                            variant="destructive"
                            disabled={deleteTarget === null || deletingMatchId !== null}
                            onClick={() => {
                                if (deleteTarget === null) {
                                    return;
                                }

                                void handleDeleteMatch(deleteTarget);
                            }}
                        >
                            {t('match_history_delete')}
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </AppLayout>
    );
}
