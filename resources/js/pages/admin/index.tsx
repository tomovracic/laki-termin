import { Head } from '@inertiajs/react';
import type { FormEvent} from 'react';
import { useEffect, useMemo, useState } from 'react';
import { GlobalSettingsForm } from '@/components/admin/global-settings-form';
import { PaginationControls } from '@/components/admin/pagination-controls';
import { SearchInput } from '@/components/admin/search-input';
import { StatCard } from '@/components/admin/stat-card';
import { StatusBanner } from '@/components/admin/status-banner';
import { TerrainOverview } from '@/components/admin/terrain-overview';
import type {
    ApiErrorResponse,
    GlobalSetting,
    ManagedTerrain,
    ManagedUser,
} from '@/components/admin/types';
import { UserTokenManager } from '@/components/admin/user-token-manager';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/app-layout';
import { csrfHeaders } from '@/lib/csrf';
import { useI18n } from '@/lib/i18n';
import type { BreadcrumbItem } from '@/types';

type AdminDashboardProps = {
    users: ManagedUser[];
    terrains: ManagedTerrain[];
    global_setting: GlobalSetting | null;
};

const USER_SEARCH_QUERY_KEY = 'user_search';
const USER_PAGE_QUERY_KEY = 'user_page';
const TERRAIN_SEARCH_QUERY_KEY = 'terrain_search';
const TERRAIN_PAGE_QUERY_KEY = 'terrain_page';

function getInitialQueryState() {
    if (typeof window === 'undefined') {
        return {
            userSearch: '',
            userPage: 1,
            terrainSearch: '',
            terrainPage: 1,
        };
    }

    const params = new URLSearchParams(window.location.search);
    const parsePage = (value: string | null): number => {
        const parsed = Number.parseInt(value ?? '', 10);
        return Number.isInteger(parsed) && parsed > 0 ? parsed : 1;
    };

    return {
        userSearch: params.get(USER_SEARCH_QUERY_KEY) ?? '',
        userPage: parsePage(params.get(USER_PAGE_QUERY_KEY)),
        terrainSearch: params.get(TERRAIN_SEARCH_QUERY_KEY) ?? '',
        terrainPage: parsePage(params.get(TERRAIN_PAGE_QUERY_KEY)),
    };
}

export default function AdminDashboard({
    users: initialUsers,
    terrains: initialTerrains,
    global_setting: initialGlobalSetting,
}: AdminDashboardProps) {
    const { t } = useI18n();
    const breadcrumbs: BreadcrumbItem[] = [
        {
            title: t('admin'),
            href: '/admin',
        },
    ];
    const initialQueryState = useMemo(() => getInitialQueryState(), []);

    const [users, setUsers] = useState<ManagedUser[]>(initialUsers);
    const [terrains, setTerrains] = useState<ManagedTerrain[]>(initialTerrains);
    const [message, setMessage] = useState<string | null>(null);
    const [errorMessage, setErrorMessage] = useState<string | null>(null);

    const [tokenDrafts, setTokenDrafts] = useState<Record<number, string>>(() =>
        Object.fromEntries(initialUsers.map((user) => [user.id, `${user.token_count}`])),
    );
    const [savingTokenUserId, setSavingTokenUserId] = useState<number | null>(null);
    const [userSearch, setUserSearch] = useState(initialQueryState.userSearch);
    const [userPage, setUserPage] = useState(initialQueryState.userPage);
    const usersPerPage = 6;

    const [terrainName, setTerrainName] = useState<string>('');
    const [terrainDescription, setTerrainDescription] = useState<string>('');
    const [terrainErrors, setTerrainErrors] = useState<Record<string, string[]>>({});
    const [isCreatingTerrain, setIsCreatingTerrain] = useState<boolean>(false);
    const [descriptionDrafts, setDescriptionDrafts] = useState<Record<number, string>>(
        () =>
            Object.fromEntries(
                initialTerrains.map((terrain) => [terrain.id, terrain.description ?? '']),
            ),
    );
    const [savingTerrainId, setSavingTerrainId] = useState<number | null>(null);
    const [terrainSearch, setTerrainSearch] = useState(
        initialQueryState.terrainSearch,
    );
    const [terrainTab, setTerrainTab] = useState<'overview' | 'settings'>('overview');
    const [terrainPage, setTerrainPage] = useState(initialQueryState.terrainPage);
    const terrainsPerPage = 6;

    const [globalSetting, setGlobalSetting] = useState<GlobalSetting>({
        max_advance_days: initialGlobalSetting?.max_advance_days ?? 30,
        cancellation_cutoff_hours:
            initialGlobalSetting?.cancellation_cutoff_hours ?? 0,
        availability_periods: initialGlobalSetting?.availability_periods ?? [
            {
                from: '08:00',
                to: '22:00',
                slot_duration_minutes: 60,
            },
        ],
    });
    const [globalErrors, setGlobalErrors] = useState<Record<string, string[]>>({});
    const [isSavingGlobalSettings, setIsSavingGlobalSettings] = useState(false);

    const totalTokens = useMemo(
        () => users.reduce((sum, user) => sum + user.token_count, 0),
        [users],
    );
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

    const userTotalPages = Math.max(
        1,
        Math.ceil(filteredUsers.length / usersPerPage),
    );
    const pagedUsers = useMemo(() => {
        const currentPage = Math.min(userPage, userTotalPages);
        const start = (currentPage - 1) * usersPerPage;
        return filteredUsers.slice(start, start + usersPerPage);
    }, [filteredUsers, userPage, userTotalPages]);

    const filteredTerrains = useMemo(() => {
        const term = terrainSearch.trim().toLowerCase();
        if (term === '') {
            return terrains;
        }

        return terrains.filter(
            (terrain) =>
                terrain.name.toLowerCase().includes(term) ||
                terrain.code.toLowerCase().includes(term) ||
                (terrain.description ?? '').toLowerCase().includes(term),
        );
    }, [terrainSearch, terrains]);

    const terrainTotalPages = Math.max(
        1,
        Math.ceil(filteredTerrains.length / terrainsPerPage),
    );
    const pagedTerrains = useMemo(() => {
        const currentPage = Math.min(terrainPage, terrainTotalPages);
        const start = (currentPage - 1) * terrainsPerPage;
        return filteredTerrains.slice(start, start + terrainsPerPage);
    }, [filteredTerrains, terrainPage, terrainTotalPages]);

    useEffect(() => {
        if (userPage > userTotalPages) {
            setUserPage(userTotalPages);
        }
    }, [userPage, userTotalPages]);

    useEffect(() => {
        if (terrainPage > terrainTotalPages) {
            setTerrainPage(terrainTotalPages);
        }
    }, [terrainPage, terrainTotalPages]);

    useEffect(() => {
        if (typeof window === 'undefined') {
            return;
        }

        const params = new URLSearchParams(window.location.search);
        const setOrDelete = (key: string, value: string, defaultValue: string) => {
            if (value === defaultValue) {
                params.delete(key);
                return;
            }

            params.set(key, value);
        };

        setOrDelete(USER_SEARCH_QUERY_KEY, userSearch, '');
        setOrDelete(USER_PAGE_QUERY_KEY, `${userPage}`, '1');
        setOrDelete(TERRAIN_SEARCH_QUERY_KEY, terrainSearch, '');
        setOrDelete(TERRAIN_PAGE_QUERY_KEY, `${terrainPage}`, '1');

        const query = params.toString();
        const url = query === '' ? window.location.pathname : `${window.location.pathname}?${query}`;
        window.history.replaceState({}, '', url);
    }, [terrainPage, terrainSearch, userPage, userSearch]);

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

    async function handleCreateTerrain(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();
        setIsCreatingTerrain(true);
        setTerrainErrors({});
        setMessage(null);
        setErrorMessage(null);

        const response = await fetch('/terrains', {
            method: 'POST',
            headers: {
                Accept: 'application/json',
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                ...csrfHeaders(),
            },
            body: JSON.stringify({
                name: terrainName,
                description: terrainDescription || null,
            }),
        });

        if (!response.ok) {
            const error = await parseError(response);
            setTerrainErrors(error.errors ?? {});
            setErrorMessage(error.message ?? t('unable_create_terrain'));
            setIsCreatingTerrain(false);
            return;
        }

        const payload = (await response.json()) as { data: ManagedTerrain };
        setTerrains((current) =>
            [...current, payload.data].sort((a, b) => a.name.localeCompare(b.name)),
        );
        setDescriptionDrafts((current) => ({
            ...current,
            [payload.data.id]: payload.data.description ?? '',
        }));
        setTerrainName('');
        setTerrainDescription('');
        setTerrainPage(1);
        setMessage(`${t('terrain_created')}: "${payload.data.name}".`);
        setIsCreatingTerrain(false);
    }

    async function patchTerrain(
        terrain: ManagedTerrain,
        body: Record<string, unknown>,
        successMessage: string,
    ) {
        setSavingTerrainId(terrain.id);
        setMessage(null);
        setErrorMessage(null);

        const response = await fetch(`/terrains/${terrain.id}`, {
            method: 'PATCH',
            headers: {
                Accept: 'application/json',
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                ...csrfHeaders(),
            },
            body: JSON.stringify(body),
        });

        if (!response.ok) {
            const error = await parseError(response);
            setErrorMessage(error.message ?? t('unable_update_terrain'));
            setSavingTerrainId(null);
            return;
        }

        const payload = (await response.json()) as { data: ManagedTerrain };
        setTerrains((current) =>
            current.map((entry) => (entry.id === payload.data.id ? payload.data : entry)),
        );
        setDescriptionDrafts((current) => ({
            ...current,
            [payload.data.id]: payload.data.description ?? '',
        }));
        setMessage(successMessage);
        setSavingTerrainId(null);
    }

    async function handleSaveTerrainDescription(terrain: ManagedTerrain) {
        await patchTerrain(
            terrain,
            { description: descriptionDrafts[terrain.id] ?? null },
            `${t('saved_description_for')} ${terrain.name}.`,
        );
    }

    async function handleToggleTerrainStatus(terrain: ManagedTerrain) {
        await patchTerrain(
            terrain,
            { is_active: !terrain.is_active },
            `${terrain.name} ${terrain.is_active ? t('terrain_now_inactive') : t('terrain_now_active')}.`,
        );
    }

    async function handleSaveGlobalSetting(
        event: FormEvent<HTMLFormElement>,
    ): Promise<void> {
        event.preventDefault();
        setIsSavingGlobalSettings(true);
        setGlobalErrors({});
        setMessage(null);
        setErrorMessage(null);

        const response = await fetch('/terrain-settings/upsert', {
            method: 'POST',
            headers: {
                Accept: 'application/json',
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                ...csrfHeaders(),
            },
            body: JSON.stringify(globalSetting),
        });

        if (!response.ok) {
            const error = await parseError(response);
            setGlobalErrors(error.errors ?? {});
            setErrorMessage(error.message ?? t('unable_save_global_settings'));
            setIsSavingGlobalSettings(false);
            return;
        }

        const payload = (await response.json()) as { data: GlobalSetting };
        setGlobalSetting(payload.data);
        setMessage(t('global_settings_saved'));
        setIsSavingGlobalSettings(false);
    }

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={t('admin')} />

            <div className="flex h-full flex-1 flex-col gap-4 overflow-x-auto rounded-xl p-4">
                <div className="rounded-xl border bg-gradient-to-br from-muted/40 via-background to-background p-5">
                    <h1 className="text-2xl font-semibold tracking-tight">
                        {t('admin_dashboard')}
                    </h1>
                    <p className="mt-1 text-sm text-muted-foreground">
                        {t('admin_dashboard_description')}
                    </p>
                </div>

                <div className="grid gap-4 md:grid-cols-3">
                    <StatCard label={t('total_users')} value={users.length} />
                    <StatCard label={t('total_terrains')} value={terrains.length} />
                    <StatCard label={t('total_allocated_tokens')} value={totalTokens} />
                </div>

                <StatusBanner message={message} error={errorMessage} />

                <div className="space-y-3">
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
                        page={Math.min(userPage, userTotalPages)}
                        totalPages={userTotalPages}
                        onPageChange={setUserPage}
                    />
                </div>

                <div className="space-y-3">
                    <div className="grid grid-cols-2 gap-2 rounded-md border border-border/70 p-1 sm:w-fit">
                        <Button
                            type="button"
                            variant={terrainTab === 'overview' ? 'default' : 'ghost'}
                            onClick={() => setTerrainTab('overview')}
                        >
                            {t('overview')}
                        </Button>
                        <Button
                            type="button"
                            variant={terrainTab === 'settings' ? 'default' : 'ghost'}
                            onClick={() => setTerrainTab('settings')}
                        >
                            {t('settings')}
                        </Button>
                    </div>
                    {terrainTab === 'overview' && (
                        <SearchInput
                            value={terrainSearch}
                            placeholder={t('search_terrains_placeholder')}
                            onChange={(value) => {
                                setTerrainSearch(value);
                                setTerrainPage(1);
                            }}
                        />
                    )}
                    <TerrainOverview
                        activeTab={terrainTab}
                        terrains={pagedTerrains}
                        descriptionDrafts={descriptionDrafts}
                        savingTerrainId={savingTerrainId}
                        terrainName={terrainName}
                        terrainDescription={terrainDescription}
                        terrainErrors={terrainErrors}
                        isCreatingTerrain={isCreatingTerrain}
                        settingsContent={(
                            <GlobalSettingsForm
                                value={globalSetting}
                                isSaving={isSavingGlobalSettings}
                                errors={globalErrors}
                                onChange={setGlobalSetting}
                                onSubmit={(event) => void handleSaveGlobalSetting(event)}
                            />
                        )}
                        onDescriptionChange={(terrainId, value) =>
                            setDescriptionDrafts((current) => ({
                                ...current,
                                [terrainId]: value,
                            }))
                        }
                        onSaveDescription={(terrain) =>
                            void handleSaveTerrainDescription(terrain)
                        }
                        onToggleStatus={(terrain) =>
                            void handleToggleTerrainStatus(terrain)
                        }
                        onTerrainNameChange={setTerrainName}
                        onTerrainDescriptionChange={setTerrainDescription}
                        onCreateTerrain={(event) => void handleCreateTerrain(event)}
                    />
                    {terrainTab === 'overview' && (
                        <PaginationControls
                            page={Math.min(terrainPage, terrainTotalPages)}
                            totalPages={terrainTotalPages}
                            onPageChange={setTerrainPage}
                        />
                    )}
                </div>
            </div>
        </AppLayout>
    );
}
