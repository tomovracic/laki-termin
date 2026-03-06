import { Head } from '@inertiajs/react';
import { useEffect, useMemo, useState } from 'react';
import { AdminSectionLayout } from '@/components/admin/admin-section-layout';
import { PaginationControls } from '@/components/admin/pagination-controls';
import { SearchInput } from '@/components/admin/search-input';
import { StatusBanner } from '@/components/admin/status-banner';
import type { ApiErrorResponse, ManagedUser } from '@/components/admin/types';
import { UserTokenManager } from '@/components/admin/user-token-manager';
import { csrfHeaders } from '@/lib/csrf';
import { useI18n } from '@/lib/i18n';

type AdminUsersPageProps = {
    users: ManagedUser[];
};

const USER_SEARCH_QUERY_KEY = 'user_search';
const USER_PAGE_QUERY_KEY = 'user_page';

function getInitialQueryState() {
    if (typeof window === 'undefined') {
        return { userSearch: '', userPage: 1 };
    }

    const params = new URLSearchParams(window.location.search);
    const parsedPage = Number.parseInt(params.get(USER_PAGE_QUERY_KEY) ?? '', 10);

    return {
        userSearch: params.get(USER_SEARCH_QUERY_KEY) ?? '',
        userPage: Number.isInteger(parsedPage) && parsedPage > 0 ? parsedPage : 1,
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
    const [userSearch, setUserSearch] = useState(initialQueryState.userSearch);
    const [userPage, setUserPage] = useState(initialQueryState.userPage);
    const usersPerPage = 8;

    const filteredUsers = useMemo(() => {
        const term = userSearch.trim().toLowerCase();
        if (term === '') {
            return users;
        }

        return users.filter(
            (user) =>
                `${user.first_name} ${user.last_name}`.toLowerCase().includes(term) ||
                user.email.toLowerCase().includes(term),
        );
    }, [userSearch, users]);
    const totalPages = Math.max(1, Math.ceil(filteredUsers.length / usersPerPage));
    const pagedUsers = useMemo(() => {
        const currentPage = Math.min(userPage, totalPages);
        const start = (currentPage - 1) * usersPerPage;
        return filteredUsers.slice(start, start + usersPerPage);
    }, [filteredUsers, userPage, totalPages]);

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

        const query = params.toString();
        const url = query === '' ? window.location.pathname : `${window.location.pathname}?${query}`;
        window.history.replaceState({}, '', url);
    }, [userPage, userSearch]);

    async function parseError(response: Response): Promise<ApiErrorResponse> {
        try {
            return (await response.json()) as ApiErrorResponse;
        } catch {
            return { message: t('unexpected_server_response') };
        }
    }

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

    return (
        <AdminSectionLayout
            title={t('users_overview')}
            description={t('users_overview_description')}
        >
            <Head title={t('admin_users')} />

            <StatusBanner message={message} error={errorMessage} />

            <SearchInput
                value={userSearch}
                placeholder={t('search_users_placeholder')}
                onChange={(value) => {
                    setUserSearch(value);
                    setUserPage(1);
                }}
            />

            <UserTokenManager
                users={pagedUsers}
                tokenDrafts={tokenDrafts}
                savingUserId={savingTokenUserId}
                onDraftChange={(userId, value) =>
                    setTokenDrafts((current) => ({
                        ...current,
                        [userId]: value,
                    }))
                }
                onSave={(user) => void handleTokenSave(user)}
            />

            <PaginationControls
                page={Math.min(userPage, totalPages)}
                totalPages={totalPages}
                onPageChange={setUserPage}
            />
        </AdminSectionLayout>
    );
}
