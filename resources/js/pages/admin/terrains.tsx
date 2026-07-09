import { Head } from '@inertiajs/react';
import type { FormEvent} from 'react';
import { useEffect, useMemo, useState } from 'react';
import { AdminSectionLayout } from '@/components/admin/admin-section-layout';
import { GlobalSettingsForm } from '@/components/admin/global-settings-form';
import { InactivePeriodsForm } from '@/components/admin/inactive-periods-form';
import { InactivePeriodsList } from '@/components/admin/inactive-periods-list';
import { PaginationControls } from '@/components/admin/pagination-controls';
import { SearchInput } from '@/components/admin/search-input';
import { StatusBanner } from '@/components/admin/status-banner';
import { TerrainOverview } from '@/components/admin/terrain-overview';
import { TerrainUsageRulesForm } from '@/components/admin/terrain-usage-rules-form';
import type {
    ApiErrorResponse,
    GlobalSetting,
    InactivePeriod,
    InactivePeriodFormValue,
    ManagedTerrain,
} from '@/components/admin/types';
import { Button } from '@/components/ui/button';
import { csrfHeaders } from '@/lib/csrf';
import { useI18n } from '@/lib/i18n';
import type { TerrainUsageRule } from '@/lib/terrain-usage-rule-icons';

type AdminTerrainsPageProps = {
    terrains: ManagedTerrain[];
    global_setting: GlobalSetting | null;
    inactive_periods: InactivePeriod[];
    terrain_usage_rules: TerrainUsageRule[];
};

function todayIsoDate(): string {
    const today = new Date();
    const year = today.getFullYear();
    const month = `${today.getMonth() + 1}`.padStart(2, '0');
    const day = `${today.getDate()}`.padStart(2, '0');

    return `${year}-${month}-${day}`;
}

const TERRAIN_SEARCH_QUERY_KEY = 'terrain_search';
const TERRAIN_PAGE_QUERY_KEY = 'terrain_page';

function getInitialQueryState() {
    if (typeof window === 'undefined') {
        return { terrainSearch: '', terrainPage: 1 };
    }

    const params = new URLSearchParams(window.location.search);
    const parsedPage = Number.parseInt(params.get(TERRAIN_PAGE_QUERY_KEY) ?? '', 10);

    return {
        terrainSearch: params.get(TERRAIN_SEARCH_QUERY_KEY) ?? '',
        terrainPage:
            Number.isInteger(parsedPage) && parsedPage > 0 ? parsedPage : 1,
    };
}

export default function AdminTerrainsPage({
    terrains: initialTerrains,
    global_setting: initialGlobalSetting,
    inactive_periods: initialInactivePeriods,
    terrain_usage_rules: initialTerrainUsageRules,
}: AdminTerrainsPageProps) {
    const { t } = useI18n();
    const initialQueryState = useMemo(() => getInitialQueryState(), []);

    const [terrains, setTerrains] = useState<ManagedTerrain[]>(initialTerrains);
    const [message, setMessage] = useState<string | null>(null);
    const [errorMessage, setErrorMessage] = useState<string | null>(null);
    const [terrainSearch, setTerrainSearch] = useState(
        initialQueryState.terrainSearch,
    );
    const [activeTab, setActiveTab] = useState<
        'overview' | 'settings' | 'usage_rules' | 'blocked_days'
    >('overview');
    const [terrainPage, setTerrainPage] = useState(initialQueryState.terrainPage);
    const terrainsPerPage = 8;

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

    const [inactivePeriods, setInactivePeriods] = useState<InactivePeriod[]>(
        initialInactivePeriods,
    );
    const [inactivePeriodForm, setInactivePeriodForm] = useState<InactivePeriodFormValue>(() => ({
        block_type: 'full_day',
        from_date: todayIsoDate(),
        to_date: todayIsoDate(),
        from_time: '20:00',
        to_time: '23:00',
        terrain_id: null,
        reason: 'rain',
        note: '',
    }));
    const [inactivePeriodErrors, setInactivePeriodErrors] = useState<
        Record<string, string[]>
    >({});
    const [isAddingInactivePeriod, setIsAddingInactivePeriod] = useState(false);
    const [deletingInactivePeriodId, setDeletingInactivePeriodId] = useState<
        number | null
    >(null);
    const [terrainUsageRules, setTerrainUsageRules] = useState<TerrainUsageRule[]>(
        initialTerrainUsageRules,
    );
    const [terrainUsageRulesError, setTerrainUsageRulesError] = useState<
        string | undefined
    >();
    const [creatingTerrainUsageRule, setCreatingTerrainUsageRule] = useState(false);
    const [updatingTerrainUsageRuleIndex, setUpdatingTerrainUsageRuleIndex] =
        useState<number | null>(null);
    const [deletingTerrainUsageRuleIndex, setDeletingTerrainUsageRuleIndex] =
        useState<number | null>(null);

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
    const totalPages = Math.max(1, Math.ceil(filteredTerrains.length / terrainsPerPage));
    const pagedTerrains = useMemo(() => {
        const currentPage = Math.min(terrainPage, totalPages);
        const start = (currentPage - 1) * terrainsPerPage;
        return filteredTerrains.slice(start, start + terrainsPerPage);
    }, [filteredTerrains, terrainPage, totalPages]);

    useEffect(() => {
        if (terrainPage > totalPages) {
            setTerrainPage(totalPages);
        }
    }, [terrainPage, totalPages]);

    useEffect(() => {
        if (typeof window === 'undefined') {
            return;
        }

        const params = new URLSearchParams(window.location.search);
        if (terrainSearch === '') {
            params.delete(TERRAIN_SEARCH_QUERY_KEY);
        } else {
            params.set(TERRAIN_SEARCH_QUERY_KEY, terrainSearch);
        }

        if (terrainPage === 1) {
            params.delete(TERRAIN_PAGE_QUERY_KEY);
        } else {
            params.set(TERRAIN_PAGE_QUERY_KEY, `${terrainPage}`);
        }

        const query = params.toString();
        const url = query === '' ? window.location.pathname : `${window.location.pathname}?${query}`;
        window.history.replaceState({}, '', url);
    }, [terrainPage, terrainSearch]);

    async function parseError(response: Response): Promise<ApiErrorResponse> {
        try {
            return (await response.json()) as ApiErrorResponse;
        } catch {
            return { message: t('unexpected_server_response') };
        }
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

    function terrainUsageRulePayload(rule: TerrainUsageRule) {
        return {
            icon: rule.icon,
            text: rule.text.trim(),
            emphasis: rule.emphasis ?? null,
        };
    }

    async function handleCreateTerrainUsageRule(
        rule: TerrainUsageRule,
    ): Promise<void> {
        setCreatingTerrainUsageRule(true);
        setTerrainUsageRulesError(undefined);
        setMessage(null);
        setErrorMessage(null);

        const response = await fetch('/app-settings/terrain-usage-rules', {
            method: 'POST',
            headers: {
                Accept: 'application/json',
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                ...csrfHeaders(),
            },
            body: JSON.stringify(terrainUsageRulePayload(rule)),
        });

        if (!response.ok) {
            const error = await parseError(response);
            setTerrainUsageRulesError(
                Object.values(error.errors ?? {})[0]?.[0]
                    ?? error.message
                    ?? t('unable_save_terrain_usage_rules'),
            );
            setCreatingTerrainUsageRule(false);
            throw new Error('create_failed');
        }

        const payload = (await response.json()) as {
            data: { terrain_usage_rules: TerrainUsageRule[] };
        };
        setTerrainUsageRules(payload.data.terrain_usage_rules);
        setMessage(t('terrain_usage_rules_saved'));
        setCreatingTerrainUsageRule(false);
    }

    async function handleUpdateTerrainUsageRule(
        index: number,
        rule: TerrainUsageRule,
    ): Promise<void> {
        setUpdatingTerrainUsageRuleIndex(index);
        setTerrainUsageRulesError(undefined);
        setMessage(null);
        setErrorMessage(null);

        const response = await fetch(`/app-settings/terrain-usage-rules/${index}`, {
            method: 'PATCH',
            headers: {
                Accept: 'application/json',
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                ...csrfHeaders(),
            },
            body: JSON.stringify(terrainUsageRulePayload(rule)),
        });

        if (!response.ok) {
            const error = await parseError(response);
            setTerrainUsageRulesError(
                Object.values(error.errors ?? {})[0]?.[0]
                    ?? error.message
                    ?? t('unable_save_terrain_usage_rules'),
            );
            setUpdatingTerrainUsageRuleIndex(null);
            throw new Error('update_failed');
        }

        const payload = (await response.json()) as {
            data: { terrain_usage_rules: TerrainUsageRule[] };
        };
        setTerrainUsageRules(payload.data.terrain_usage_rules);
        setMessage(t('terrain_usage_rules_saved'));
        setUpdatingTerrainUsageRuleIndex(null);
    }

    async function handleDeleteTerrainUsageRule(index: number): Promise<void> {
        setDeletingTerrainUsageRuleIndex(index);
        setTerrainUsageRulesError(undefined);
        setMessage(null);
        setErrorMessage(null);

        const response = await fetch(`/app-settings/terrain-usage-rules/${index}`, {
            method: 'DELETE',
            headers: {
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                ...csrfHeaders(),
            },
        });

        if (!response.ok) {
            const error = await parseError(response);
            setErrorMessage(
                error.message ?? t('unable_save_terrain_usage_rules'),
            );
            setDeletingTerrainUsageRuleIndex(null);
            return;
        }

        const payload = (await response.json()) as {
            data: { terrain_usage_rules: TerrainUsageRule[] };
        };
        setTerrainUsageRules(payload.data.terrain_usage_rules);
        setMessage(t('terrain_usage_rules_saved'));
        setDeletingTerrainUsageRuleIndex(null);
    }

    async function handleAddInactivePeriod(
        event: FormEvent<HTMLFormElement>,
    ): Promise<void> {
        event.preventDefault();
        setIsAddingInactivePeriod(true);
        setInactivePeriodErrors({});
        setMessage(null);
        setErrorMessage(null);

        const response = await fetch('/terrain-inactive-periods', {
            method: 'POST',
            headers: {
                Accept: 'application/json',
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                ...csrfHeaders(),
            },
            body: JSON.stringify({
                block_type: inactivePeriodForm.block_type,
                from_date: inactivePeriodForm.from_date,
                to_date:
                    inactivePeriodForm.block_type === 'time_range'
                        ? inactivePeriodForm.from_date
                        : inactivePeriodForm.to_date,
                from_time:
                    inactivePeriodForm.block_type === 'time_range'
                        ? inactivePeriodForm.from_time
                        : undefined,
                to_time:
                    inactivePeriodForm.block_type === 'time_range'
                        ? inactivePeriodForm.to_time
                        : undefined,
                terrain_id: inactivePeriodForm.terrain_id,
                reason: inactivePeriodForm.reason,
                note: inactivePeriodForm.note.trim() === ''
                    ? null
                    : inactivePeriodForm.note.trim(),
            }),
        });

        if (!response.ok) {
            const error = await parseError(response);
            setInactivePeriodErrors(error.errors ?? {});
            setErrorMessage(error.message ?? t('unable_add_blocked_day'));
            setIsAddingInactivePeriod(false);
            return;
        }

        const payload = (await response.json()) as { data: InactivePeriod };
        setInactivePeriods((current) =>
            [...current, payload.data].sort((left, right) =>
                left.from_date.localeCompare(right.from_date),
            ),
        );
        setInactivePeriodForm({
            block_type: 'full_day',
            from_date: todayIsoDate(),
            to_date: todayIsoDate(),
            from_time: '20:00',
            to_time: '23:00',
            terrain_id: null,
            reason: 'rain',
            note: '',
        });
        setMessage(t('blocked_day_added'));
        setIsAddingInactivePeriod(false);
    }

    async function handleDeleteInactivePeriod(period: InactivePeriod): Promise<void> {
        setDeletingInactivePeriodId(period.id);
        setMessage(null);
        setErrorMessage(null);

        const response = await fetch(`/terrain-inactive-periods/${period.id}`, {
            method: 'DELETE',
            headers: {
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                ...csrfHeaders(),
            },
        });

        if (!response.ok) {
            const error = await parseError(response);
            setErrorMessage(error.message ?? t('unable_remove_blocked_day'));
            setDeletingInactivePeriodId(null);
            return;
        }

        setInactivePeriods((current) =>
            current.filter((entry) => entry.id !== period.id),
        );
        setMessage(t('blocked_day_removed'));
        setDeletingInactivePeriodId(null);
    }

    return (
        <AdminSectionLayout
            title={t('terrains_overview')}
            description={t('terrains_overview_description')}
            showHeader={false}
        >
            <Head title={t('admin_terrains')} />

            <StatusBanner message={message} error={errorMessage} />

            <div className="grid grid-cols-2 gap-2 rounded-md border border-border/70 p-1 sm:grid-cols-4 sm:w-fit">
                <Button
                    type="button"
                    variant={activeTab === 'overview' ? 'default' : 'ghost'}
                    onClick={() => setActiveTab('overview')}
                >
                    {t('overview')}
                </Button>
                <Button
                    type="button"
                    variant={activeTab === 'settings' ? 'default' : 'ghost'}
                    onClick={() => setActiveTab('settings')}
                >
                    {t('settings')}
                </Button>
                <Button
                    type="button"
                    variant={activeTab === 'usage_rules' ? 'default' : 'ghost'}
                    onClick={() => setActiveTab('usage_rules')}
                >
                    {t('rules_short')}
                </Button>
                <Button
                    type="button"
                    variant={activeTab === 'blocked_days' ? 'default' : 'ghost'}
                    onClick={() => setActiveTab('blocked_days')}
                >
                    {t('blocked_days_tab')}
                </Button>
            </div>

            {activeTab === 'overview' && (
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
                activeTab={activeTab}
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
                usageRulesContent={(
                    <TerrainUsageRulesForm
                        rules={terrainUsageRules}
                        creatingRule={creatingTerrainUsageRule}
                        updatingRuleIndex={updatingTerrainUsageRuleIndex}
                        deletingRuleIndex={deletingTerrainUsageRuleIndex}
                        error={terrainUsageRulesError}
                        onCreate={handleCreateTerrainUsageRule}
                        onUpdate={handleUpdateTerrainUsageRule}
                        onDelete={handleDeleteTerrainUsageRule}
                    />
                )}
                blockedDaysContent={(
                    <div className="space-y-6">
                        <InactivePeriodsForm
                            value={inactivePeriodForm}
                            terrains={terrains}
                            isSaving={isAddingInactivePeriod}
                            errors={inactivePeriodErrors}
                            onChange={setInactivePeriodForm}
                            onSubmit={(event) => void handleAddInactivePeriod(event)}
                        />
                        <InactivePeriodsList
                            periods={inactivePeriods}
                            deletingPeriodId={deletingInactivePeriodId}
                            onDelete={(period) => void handleDeleteInactivePeriod(period)}
                        />
                    </div>
                )}
                onDescriptionChange={(terrainId, value) =>
                    setDescriptionDrafts((current) => ({
                        ...current,
                        [terrainId]: value,
                    }))
                }
                onSaveDescription={(terrain) => void handleSaveTerrainDescription(terrain)}
                onToggleStatus={(terrain) => void handleToggleTerrainStatus(terrain)}
                onTerrainNameChange={setTerrainName}
                onTerrainDescriptionChange={setTerrainDescription}
                onCreateTerrain={(event) => void handleCreateTerrain(event)}
            />

            {activeTab === 'overview' && (
                <PaginationControls
                    page={Math.min(terrainPage, totalPages)}
                    totalPages={totalPages}
                    onPageChange={setTerrainPage}
                />
            )}
        </AdminSectionLayout>
    );
}
