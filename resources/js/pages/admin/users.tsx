import { Head, router } from '@inertiajs/react';
import type { FormEvent } from 'react';
import { useCallback, useEffect, useMemo, useState } from 'react';
import { AdminSectionLayout } from '@/components/admin/admin-section-layout';
import { PaginationControls } from '@/components/admin/pagination-controls';
import { SearchInput } from '@/components/admin/search-input';
import { StatusBanner } from '@/components/admin/status-banner';
import type { AdminUserReservation, ApiErrorResponse, ManagedUser } from '@/components/admin/types';
import { UserTokenManager } from '@/components/admin/user-token-manager';
import InputError from '@/components/input-error';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
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
import { csrfHeaders } from '@/lib/csrf';
import { useI18n } from '@/lib/i18n';

type AdminUsersPageProps = {
    users: ManagedUser[];
};

type UserTab = 'existing' | 'invited';

type PaginatedReservationsResponse = {
    data: AdminUserReservation[];
    meta?: {
        current_page?: number;
        last_page?: number;
        total?: number;
    };
};

const USER_SEARCH_QUERY_KEY = 'user_search';
const USER_PAGE_QUERY_KEY = 'user_page';
const USER_TAB_QUERY_KEY = 'user_tab';

function getInitialQueryState() {
    if (typeof window === 'undefined') {
        return { userSearch: '', userPage: 1, userTab: 'existing' as UserTab };
    }

    const params = new URLSearchParams(window.location.search);
    const parsedPage = Number.parseInt(params.get(USER_PAGE_QUERY_KEY) ?? '', 10);
    const userTabParam = params.get(USER_TAB_QUERY_KEY);
    const userTab: UserTab = userTabParam === 'invited' ? 'invited' : 'existing';

    return {
        userSearch: params.get(USER_SEARCH_QUERY_KEY) ?? '',
        userPage: Number.isInteger(parsedPage) && parsedPage > 0 ? parsedPage : 1,
        userTab,
    };
}

export default function AdminUsersPage({ users: initialUsers }: AdminUsersPageProps) {
    const { t } = useI18n();
    const initialQueryState = useMemo(() => getInitialQueryState(), []);

    const [users, setUsers] = useState<ManagedUser[]>(initialUsers);
    const [tokenDrafts, setTokenDrafts] = useState<Record<number, string>>(() =>
        Object.fromEntries(initialUsers.map((user) => [user.id, `${user.token_count}`])),
    );
    const [savingTokenUserId, setSavingTokenUserId] = useState<number | null>(null);
    const [message, setMessage] = useState<string | null>(null);
    const [errorMessage, setErrorMessage] = useState<string | null>(null);
    const [userErrors, setUserErrors] = useState<Record<string, string[]>>({});
    const [isCreatingUser, setIsCreatingUser] = useState(false);
    const [email, setEmail] = useState('');
    const [isCreateUserModalOpen, setIsCreateUserModalOpen] = useState(false);
    const [userSearch, setUserSearch] = useState(initialQueryState.userSearch);
    const [userPage, setUserPage] = useState(initialQueryState.userPage);
    const [userTab, setUserTab] = useState<UserTab>(initialQueryState.userTab);
    const [isReservationsModalOpen, setIsReservationsModalOpen] = useState(false);
    const [selectedReservationUser, setSelectedReservationUser] = useState<ManagedUser | null>(null);
    const [selectedUserReservations, setSelectedUserReservations] = useState<AdminUserReservation[]>([]);
    const [reservationsPage, setReservationsPage] = useState(1);
    const [reservationsTotalPages, setReservationsTotalPages] = useState(1);
    const [isLoadingReservations, setIsLoadingReservations] = useState(false);
    const usersPerPage = 8;

    const usersInActiveTab = useMemo(
        () =>
            users.filter((user) =>
                userTab === 'invited'
                    ? user.invitation_status === 'pending'
                    : user.invitation_status === 'active',
            ),
        [userTab, users],
    );
    const filteredUsers = useMemo(() => {
        const term = userSearch.trim().toLowerCase();
        if (term === '') {
            return usersInActiveTab;
        }

        return usersInActiveTab.filter(
            (user) =>
                `${user.first_name} ${user.last_name}`.toLowerCase().includes(term) ||
                user.email.toLowerCase().includes(term),
        );
    }, [userSearch, usersInActiveTab]);
    const totalPages = Math.max(1, Math.ceil(filteredUsers.length / usersPerPage));
    const pagedUsers = useMemo(() => {
        const currentPage = Math.min(userPage, totalPages);
        const start = (currentPage - 1) * usersPerPage;
        return filteredUsers.slice(start, start + usersPerPage);
    }, [filteredUsers, userPage, totalPages]);

    useEffect(() => {
        setUsers(initialUsers);
        setTokenDrafts(
            Object.fromEntries(initialUsers.map((user) => [user.id, `${user.token_count}`])),
        );
    }, [initialUsers]);

    useEffect(() => {
        if (userPage > totalPages) {
            setUserPage(totalPages);
        }
    }, [totalPages, userPage]);

    useEffect(() => {
        if (typeof window === 'undefined') {
            return;
        }

        const params = new URLSearchParams(window.location.search);
        if (userSearch === '') {
            params.delete(USER_SEARCH_QUERY_KEY);
        } else {
            params.set(USER_SEARCH_QUERY_KEY, userSearch);
        }

        if (userPage === 1) {
            params.delete(USER_PAGE_QUERY_KEY);
        } else {
            params.set(USER_PAGE_QUERY_KEY, `${userPage}`);
        }

        if (userTab === 'existing') {
            params.delete(USER_TAB_QUERY_KEY);
        } else {
            params.set(USER_TAB_QUERY_KEY, userTab);
        }

        const query = params.toString();
        const url = query === '' ? window.location.pathname : `${window.location.pathname}?${query}`;
        window.history.replaceState({}, '', url);
    }, [userPage, userSearch, userTab]);

    const parseError = useCallback(async (response: Response): Promise<ApiErrorResponse> => {
        try {
            return (await response.json()) as ApiErrorResponse;
        } catch {
            return { message: t('unexpected_server_response') };
        }
    }, [t]);

    function formatDate(value?: string | null): string {
        if (value === undefined || value === null || value === '') {
            return '';
        }

        const date = new Date(value);

        if (Number.isNaN(date.getTime())) {
            return value;
        }

        return date.toLocaleDateString(undefined, {
            year: 'numeric',
            month: '2-digit',
            day: 'numeric',
        });
    }

    function toTime(value?: string | null): string {
        if (value === undefined || value === null || value === '') {
            return '';
        }

        const timeMatch = value.match(/(?:T|\s)?(\d{2}:\d{2})/);

        if (timeMatch?.[1] !== undefined) {
            return timeMatch[1];
        }

        return value;
    }

    function reservationSlotLabel(reservation: AdminUserReservation): string {
        const date = reservation.reserved_for_date ?? reservation.slot?.starts_at ?? '';
        const fromTime = reservation.reserved_from_time ?? reservation.slot?.starts_at ?? '';
        const toTimeValue = reservation.reserved_to_time ?? reservation.slot?.ends_at ?? '';

        const displayDate = formatDate(date);
        const displayFromTime = toTime(fromTime);
        const displayToTime = toTime(toTimeValue);

        if (displayDate === '' || displayFromTime === '' || displayToTime === '') {
            return t('slot_unavailable');
        }

        return `${displayDate} • ${displayFromTime} - ${displayToTime}`;
    }

    function reservationStatusLabel(status: AdminUserReservation['display_status']): string {
        if (status === 'cancelled') {
            return t('cancelled');
        }

        if (status === 'played') {
            return t('played');
        }

        return t('pending');
    }

    function reservationStatusBadgeClassName(status: AdminUserReservation['display_status']): string {
        if (status === 'cancelled') {
            return 'border-red-200 bg-red-50 text-red-700 dark:border-red-900/60 dark:bg-red-950/30 dark:text-red-300';
        }

        if (status === 'played') {
            return 'border-emerald-200 bg-emerald-50 text-emerald-700 dark:border-emerald-900/60 dark:bg-emerald-950/30 dark:text-emerald-300';
        }

        return 'border-amber-200 bg-amber-50 text-amber-700 dark:border-amber-900/60 dark:bg-amber-950/30 dark:text-amber-300';
    }

    const loadSelectedUserReservations = useCallback(async (page: number): Promise<void> => {
        if (selectedReservationUser === null) {
            return;
        }

        setIsLoadingReservations(true);
        setErrorMessage(null);

        try {
            const response = await fetch(
                `/admin/users/${selectedReservationUser.id}/reservations?page=${page}`,
                {
                    method: 'GET',
                    headers: {
                        Accept: 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                        ...csrfHeaders(),
                    },
                },
            );

            if (!response.ok) {
                const error = await parseError(response);
                setErrorMessage(error.message ?? t('unable_load_user_reservations'));
                return;
            }

            const payload = (await response.json()) as PaginatedReservationsResponse;
            setSelectedUserReservations(payload.data);
            setReservationsPage(payload.meta?.current_page ?? page);
            setReservationsTotalPages(payload.meta?.last_page ?? 1);
        } catch {
            setErrorMessage(t('unable_load_user_reservations'));
        } finally {
            setIsLoadingReservations(false);
        }
    }, [parseError, selectedReservationUser, t]);

    useEffect(() => {
        if (!isReservationsModalOpen || selectedReservationUser === null) {
            return;
        }

        void loadSelectedUserReservations(reservationsPage);
    }, [isReservationsModalOpen, loadSelectedUserReservations, reservationsPage, selectedReservationUser]);

    async function handleTokenSave(user: ManagedUser): Promise<void> {
        const value = Number.parseInt(tokenDrafts[user.id] ?? '0', 10);
        if (!Number.isInteger(value) || value < 0) {
            setErrorMessage(t('token_count_non_negative'));
            return;
        }

        setSavingTokenUserId(user.id);
        setMessage(null);
        setErrorMessage(null);

        const response = await fetch(`/users/${user.id}/token-count`, {
            method: 'PATCH',
            headers: {
                Accept: 'application/json',
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                ...csrfHeaders(),
            },
            body: JSON.stringify({ token_count: value }),
        });

        if (!response.ok) {
            const error = await parseError(response);
            setErrorMessage(error.message ?? t('unable_update_token_count'));
            setSavingTokenUserId(null);
            return;
        }

        const payload = (await response.json()) as { data: ManagedUser };
        setUsers((current) =>
            current.map((entry) =>
                entry.id === payload.data.id
                    ? { ...entry, token_count: payload.data.token_count }
                    : entry,
            ),
        );
        setMessage(
            `${t('updated_token_count_for')} ${user.first_name} ${user.last_name}.`,
        );
        setSavingTokenUserId(null);
    }

    function firstError(field: string): string | undefined {
        return userErrors[field]?.[0];
    }

    async function handleCreateUser(event: FormEvent<HTMLFormElement>): Promise<void> {
        event.preventDefault();
        setIsCreatingUser(true);
        setUserErrors({});
        setMessage(null);
        setErrorMessage(null);

        const response = await fetch('/users', {
            method: 'POST',
            headers: {
                Accept: 'application/json',
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                ...csrfHeaders(),
            },
            body: JSON.stringify({
                email,
            }),
        });

        if (!response.ok) {
            const error = await parseError(response);
            setUserErrors(error.errors ?? {});
            setErrorMessage(error.message ?? t('unable_create_user'));
            setIsCreatingUser(false);
            return;
        }

        const payload = (await response.json()) as { data: ManagedUser };
        setEmail('');
        setUserPage(1);
        setUserTab('invited');
        setIsCreateUserModalOpen(false);
        setMessage(`${t('invitation_sent_for')}: ${payload.data.email}.`);

        router.reload({
            only: ['users'],
            preserveScroll: true,
            preserveState: true,
            onFinish: () => {
                setIsCreatingUser(false);
            },
        });
    }

    function handleOpenUserReservations(user: ManagedUser): void {
        setSelectedReservationUser(user);
        setSelectedUserReservations([]);
        setReservationsTotalPages(1);
        setReservationsPage(1);
        setIsReservationsModalOpen(true);
    }

    return (
        <AdminSectionLayout
            title={t('users_overview')}
            description={t('users_overview_description')}
        >
            <Head title={t('admin_users')} />

            <StatusBanner message={message} error={errorMessage} />

            <div className="flex justify-end"
            >
                <Dialog
                    open={isCreateUserModalOpen}
                    onOpenChange={(isOpen) => {
                        setIsCreateUserModalOpen(isOpen);
                        if (!isOpen) {
                            setUserErrors({});
                        }
                    }}
                >
                    <DialogTrigger asChild>
                        <Button type="button">{t('create_user')}</Button>
                    </DialogTrigger>
                    <DialogContent className="max-h-[85vh] overflow-y-auto sm:max-w-2xl">
                        <DialogHeader>
                            <DialogTitle>{t('create_user')}</DialogTitle>
                            <DialogDescription>{t('create_user_description')}</DialogDescription>
                        </DialogHeader>
                        <form
                            onSubmit={(event) => void handleCreateUser(event)}
                            className="space-y-4"
                        >
                            <div className="grid gap-4">
                                <div className="space-y-1">
                                    <Label htmlFor="create-user-email">{t('email_address')}</Label>
                                    <Input
                                        id="create-user-email"
                                        type="email"
                                        value={email}
                                        onChange={(event) => setEmail(event.target.value)}
                                        required
                                    />
                                    <InputError message={firstError('email')} />
                                </div>
                            </div>
                            <div className="flex justify-end">
                                <Button type="submit" disabled={isCreatingUser}>
                                    {isCreatingUser ? t('creating') : t('create_user')}
                                </Button>
                            </div>
                        </form>
                    </DialogContent>
                </Dialog>
            </div>

            <Dialog
                open={isReservationsModalOpen}
                onOpenChange={(isOpen) => {
                    setIsReservationsModalOpen(isOpen);
                    if (!isOpen) {
                        setSelectedReservationUser(null);
                        setSelectedUserReservations([]);
                        setReservationsPage(1);
                        setReservationsTotalPages(1);
                    }
                }}
            >
                <DialogContent className="max-h-[85vh] overflow-y-auto sm:max-w-2xl">
                    <DialogHeader>
                        <DialogTitle>{t('user_reservations')}</DialogTitle>
                        <DialogDescription>
                            {selectedReservationUser === null
                                ? t('user_reservations_description')
                                : `${selectedReservationUser.first_name} ${selectedReservationUser.last_name}`}
                        </DialogDescription>
                    </DialogHeader>

                    <div className="space-y-3">
                        <p className="text-sm text-muted-foreground">
                            {selectedReservationUser?.email}
                        </p>

                        {isLoadingReservations ? (
                            <p className="text-sm text-muted-foreground">{t('loading')}</p>
                        ) : selectedUserReservations.length === 0 ? (
                            <p className="text-sm text-muted-foreground">{t('no_reservations_yet')}</p>
                        ) : (
                            <div className="space-y-2">
                                {selectedUserReservations.map((reservation) => (
                                    <div
                                        key={reservation.id}
                                        className="flex flex-wrap items-center justify-between gap-3 rounded-lg border p-3"
                                    >
                                        <div className="space-y-1">
                                            <p className="text-sm font-medium">
                                                {reservation.slot?.terrain?.name ?? t('terrain')}
                                            </p>
                                            <p className="text-xs text-muted-foreground">
                                                {reservationSlotLabel(reservation)}
                                            </p>
                                        </div>
                                        <Badge
                                            variant="outline"
                                            className={reservationStatusBadgeClassName(reservation.display_status)}
                                        >
                                            {reservationStatusLabel(reservation.display_status)}
                                        </Badge>
                                    </div>
                                ))}
                            </div>
                        )}

                        <PaginationControls
                            page={reservationsPage}
                            totalPages={reservationsTotalPages}
                            onPageChange={setReservationsPage}
                        />
                    </div>
                </DialogContent>
            </Dialog>

            <SearchInput
                value={userSearch}
                placeholder={t('search_users_placeholder')}
                onChange={(value) => {
                    setUserSearch(value);
                    setUserPage(1);
                }}
            />

            <div className="flex items-center gap-2">
                <Button
                    type="button"
                    variant={userTab === 'existing' ? 'default' : 'outline'}
                    onClick={() => {
                        setUserTab('existing');
                        setUserPage(1);
                    }}
                >
                    {t('existing_users_tab')}
                </Button>
                <Button
                    type="button"
                    variant={userTab === 'invited' ? 'default' : 'outline'}
                    onClick={() => {
                        setUserTab('invited');
                        setUserPage(1);
                    }}
                >
                    {t('invited_users_tab')}
                </Button>
            </div>

            <UserTokenManager
                users={pagedUsers}
                tokenDrafts={tokenDrafts}
                savingUserId={savingTokenUserId}
                showTokenControls={userTab === 'existing'}
                onDraftChange={(userId, value) =>
                    setTokenDrafts((current) => ({
                        ...current,
                        [userId]: value,
                    }))
                }
                onSave={(user) => void handleTokenSave(user)}
                onOpenReservations={handleOpenUserReservations}
            />

            <PaginationControls
                page={Math.min(userPage, totalPages)}
                totalPages={totalPages}
                onPageChange={setUserPage}
            />
        </AdminSectionLayout>
    );
}
