import { Head, router } from '@inertiajs/react';
import type { FormEvent } from 'react';
import { useMemo, useState } from 'react';
import { AdminSectionLayout } from '@/components/admin/admin-section-layout';
import { StatusBanner } from '@/components/admin/status-banner';
import InputError from '@/components/input-error';
import type { LeagueSummary, LeagueUserOption } from '@/components/league/types';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import {
    Dialog,
    DialogContent,
    DialogDescription,
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
import { csrfHeaders } from '@/lib/csrf';
import { useI18n } from '@/lib/i18n';

type AdminLeaguesPageProps = {
    leagues: LeagueSummary[];
    users: LeagueUserOption[];
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

export default function AdminLeaguesPage({ leagues, users }: AdminLeaguesPageProps) {
    const { t } = useI18n();
    const [name, setName] = useState('');
    const [rounds, setRounds] = useState('1');
    const [selectedParticipantIds, setSelectedParticipantIds] = useState<number[]>([]);
    const [isCreateModalOpen, setIsCreateModalOpen] = useState(false);
    const [isCreating, setIsCreating] = useState(false);
    const [errors, setErrors] = useState<Record<string, string[]>>({});
    const [message, setMessage] = useState<string | null>(null);
    const [errorMessage, setErrorMessage] = useState<string | null>(null);

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

    async function handleCreateLeague(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();
        setIsCreating(true);
        setErrors({});
        setErrorMessage(null);
        setMessage(null);

        try {
            const response = await fetch('/leagues', {
                method: 'POST',
                headers: {
                    Accept: 'application/json',
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    ...csrfHeaders(),
                },
                body: JSON.stringify({
                    name,
                    rounds: Number.parseInt(rounds, 10),
                    participant_ids: selectedParticipantIds,
                }),
            });

            const payload = (await response.json()) as ApiErrorResponse & { data?: LeagueSummary };

            if (!response.ok) {
                if (payload.errors) {
                    setErrors(payload.errors);
                }

                setErrorMessage(payload.message ?? t('league_unable_create'));
                return;
            }

            setMessage(t('league_created'));
            setIsCreateModalOpen(false);
            setName('');
            setRounds('1');
            setSelectedParticipantIds([]);
            router.reload({ only: ['leagues'] });
        } catch {
            setErrorMessage(t('league_unable_create'));
        } finally {
            setIsCreating(false);
        }
    }

    return (
        <AdminSectionLayout
            title={t('leagues_admin_overview')}
            description={t('leagues_admin_overview_description')}
        >
            <Head title={t('leagues_admin_overview')} />
            <StatusBanner message={message} error={errorMessage} />

            <div className="flex justify-end">
                <Dialog open={isCreateModalOpen} onOpenChange={setIsCreateModalOpen}>
                    <DialogTrigger asChild>
                        <Button>{t('league_create')}</Button>
                    </DialogTrigger>
                    <DialogContent className="max-h-[90vh] overflow-y-auto sm:max-w-lg">
                        <DialogHeader>
                            <DialogTitle>{t('league_create')}</DialogTitle>
                            <DialogDescription>{t('league_create_description')}</DialogDescription>
                        </DialogHeader>
                        <form onSubmit={handleCreateLeague} className="space-y-4">
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
                                        <label key={user.id} className="flex items-center gap-2 text-sm">
                                            <input
                                                type="checkbox"
                                                checked={selectedParticipantIds.includes(user.id)}
                                                onChange={() => toggleParticipant(user.id)}
                                            />
                                            <span>{participantToggleLabel(user)}</span>
                                        </label>
                                    ))}
                                </div>
                                <InputError message={errors.participant_ids?.[0]} />
                            </div>

                            <div className="flex justify-end gap-2">
                                <Button
                                    type="button"
                                    variant="outline"
                                    onClick={() => setIsCreateModalOpen(false)}
                                    disabled={isCreating}
                                >
                                    {t('cancel')}
                                </Button>
                                <Button type="submit" disabled={isCreating}>
                                    {isCreating ? t('creating') : t('league_create')}
                                </Button>
                            </div>
                        </form>
                    </DialogContent>
                </Dialog>
            </div>

            <div className="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
                {leagues.map((league) => (
                    <Card key={league.id}>
                        <CardHeader>
                            <CardTitle className="text-lg">{league.name}</CardTitle>
                        </CardHeader>
                        <CardContent className="space-y-3">
                            <div className="flex flex-wrap gap-2">
                                <Badge variant="outline">{roundsLabel(league.rounds, t)}</Badge>
                                <Badge variant="secondary">
                                    {league.participants_count} {t('league_participants').toLowerCase()}
                                </Badge>
                            </div>
                            <p className="text-sm text-muted-foreground">
                                {league.played_matches_count}/{league.matches_count} {t('league_matches_played').toLowerCase()}
                            </p>
                            <Button
                                variant="outline"
                                className="w-full"
                                onClick={() => router.visit(`/admin/leagues/${league.id}`)}
                            >
                                {t('league_manage')}
                            </Button>
                        </CardContent>
                    </Card>
                ))}
            </div>

            {leagues.length === 0 && (
                <p className="text-sm text-muted-foreground">{t('league_no_leagues')}</p>
            )}
        </AdminSectionLayout>
    );
}
