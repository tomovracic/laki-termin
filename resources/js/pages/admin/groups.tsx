import { Head } from '@inertiajs/react';
import type { FormEvent } from 'react';
import { useCallback, useEffect, useState } from 'react';
import { AdminSectionLayout } from '@/components/admin/admin-section-layout';
import { StatusBanner } from '@/components/admin/status-banner';
import type { ApiErrorResponse, ManagedGroup } from '@/components/admin/types';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
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

type ColorOption = {
    value: string;
    hex: string;
    label: string;
};

type AdminGroupsPageProps = {
    groups: ManagedGroup[];
    color_options: ColorOption[];
};

type GroupFormState = {
    name: string;
    color: string;
    can_access_ranking: boolean;
    can_view_all_ranking_groups: boolean;
};

const emptyForm = (defaultColor: string): GroupFormState => ({
    name: '',
    color: defaultColor,
    can_access_ranking: false,
    can_view_all_ranking_groups: false,
});

export default function AdminGroupsPage({
    groups: initialGroups,
    color_options: colorOptions,
}: AdminGroupsPageProps) {
    const { t } = useI18n();
    const defaultColor = colorOptions[0]?.value ?? 'slate';

    const [groups, setGroups] = useState<ManagedGroup[]>(initialGroups);
    const [message, setMessage] = useState<string | null>(null);
    const [errorMessage, setErrorMessage] = useState<string | null>(null);
    const [errors, setErrors] = useState<Record<string, string[]>>({});
    const [isCreateOpen, setIsCreateOpen] = useState(false);
    const [isCreating, setIsCreating] = useState(false);
    const [createForm, setCreateForm] = useState<GroupFormState>(() => emptyForm(defaultColor));
    const [editingGroup, setEditingGroup] = useState<ManagedGroup | null>(null);
    const [editForm, setEditForm] = useState<GroupFormState>(() => emptyForm(defaultColor));
    const [isSaving, setIsSaving] = useState(false);
    const [deletingGroupId, setDeletingGroupId] = useState<number | null>(null);

    useEffect(() => {
        setGroups(initialGroups);
    }, [initialGroups]);

    const parseError = useCallback(async (response: Response): Promise<ApiErrorResponse> => {
        try {
            return (await response.json()) as ApiErrorResponse;
        } catch {
            return { message: t('unexpected_server_response') };
        }
    }, [t]);

    function firstError(field: string): string | undefined {
        return errors[field]?.[0];
    }

    async function handleCreate(event: FormEvent<HTMLFormElement>): Promise<void> {
        event.preventDefault();
        setIsCreating(true);
        setErrors({});
        setMessage(null);
        setErrorMessage(null);

        const response = await fetch('/groups', {
            method: 'POST',
            headers: {
                Accept: 'application/json',
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                ...csrfHeaders(),
            },
            body: JSON.stringify(createForm),
        });

        if (!response.ok) {
            const error = await parseError(response);
            setErrors(error.errors ?? {});
            setErrorMessage(error.message ?? t('unable_create_group'));
            setIsCreating(false);
            return;
        }

        const payload = (await response.json()) as { data: ManagedGroup };
        setGroups((current) =>
            [...current, { ...payload.data, users_count: 0 }].sort((a, b) =>
                a.name.localeCompare(b.name),
            ),
        );
        setCreateForm(emptyForm(defaultColor));
        setIsCreateOpen(false);
        setMessage(t('group_created'));
        setIsCreating(false);
    }

    function openEdit(group: ManagedGroup): void {
        setEditingGroup(group);
        setEditForm({
            name: group.name,
            color: group.color,
            can_access_ranking: group.can_access_ranking,
            can_view_all_ranking_groups: group.can_view_all_ranking_groups,
        });
        setErrors({});
    }

    async function handleUpdate(event: FormEvent<HTMLFormElement>): Promise<void> {
        event.preventDefault();
        if (editingGroup === null) {
            return;
        }

        setIsSaving(true);
        setErrors({});
        setMessage(null);
        setErrorMessage(null);

        const response = await fetch(`/groups/${editingGroup.id}`, {
            method: 'PATCH',
            headers: {
                Accept: 'application/json',
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                ...csrfHeaders(),
            },
            body: JSON.stringify(editForm),
        });

        if (!response.ok) {
            const error = await parseError(response);
            setErrors(error.errors ?? {});
            setErrorMessage(error.message ?? t('unable_update_group'));
            setIsSaving(false);
            return;
        }

        const payload = (await response.json()) as { data: ManagedGroup };
        setGroups((current) =>
            current
                .map((group) =>
                    group.id === payload.data.id
                        ? {
                              ...payload.data,
                              users_count: group.users_count,
                          }
                        : group,
                )
                .sort((a, b) => a.name.localeCompare(b.name)),
        );
        setEditingGroup(null);
        setMessage(t('group_updated'));
        setIsSaving(false);
    }

    async function handleDelete(group: ManagedGroup): Promise<void> {
        setDeletingGroupId(group.id);
        setMessage(null);
        setErrorMessage(null);

        const response = await fetch(`/groups/${group.id}`, {
            method: 'DELETE',
            headers: {
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                ...csrfHeaders(),
            },
        });

        if (!response.ok) {
            const error = await parseError(response);
            setErrorMessage(error.message ?? t('unable_delete_group'));
            setDeletingGroupId(null);
            return;
        }

        setGroups((current) => current.filter((entry) => entry.id !== group.id));
        setMessage(t('group_deleted'));
        setDeletingGroupId(null);
    }

    function renderColorPicker(
        value: string,
        onChange: (color: string) => void,
        idPrefix: string,
    ): React.ReactNode {
        return (
            <div className="flex flex-wrap gap-2">
                {colorOptions.map((option) => {
                    const isSelected = value === option.value;

                    return (
                        <button
                            key={option.value}
                            type="button"
                            id={`${idPrefix}-${option.value}`}
                            title={option.label}
                            aria-label={option.label}
                            aria-pressed={isSelected}
                            onClick={() => onChange(option.value)}
                            className={`h-8 w-8 rounded-full border-2 transition ${
                                isSelected
                                    ? 'border-foreground scale-110'
                                    : 'border-transparent opacity-80 hover:opacity-100'
                            }`}
                            style={{ backgroundColor: option.hex }}
                        />
                    );
                })}
            </div>
        );
    }

    function renderPermissionFields(
        form: GroupFormState,
        setForm: (updater: (current: GroupFormState) => GroupFormState) => void,
        idPrefix: string,
    ): React.ReactNode {
        return (
            <div className="space-y-3">
                <div className="flex items-start gap-3">
                    <Checkbox
                        id={`${idPrefix}-access-ranking`}
                        checked={form.can_access_ranking}
                        onCheckedChange={(checked) =>
                            setForm((current) => ({
                                ...current,
                                can_access_ranking: checked === true,
                                can_view_all_ranking_groups:
                                    checked === true ? current.can_view_all_ranking_groups : false,
                            }))
                        }
                    />
                    <div className="space-y-1">
                        <Label htmlFor={`${idPrefix}-access-ranking`}>
                            {t('group_permission_access_ranking')}
                        </Label>
                        <p className="text-xs text-muted-foreground">
                            {t('group_permission_access_ranking_help')}
                        </p>
                    </div>
                </div>
                <div className="flex items-start gap-3">
                    <Checkbox
                        id={`${idPrefix}-view-all`}
                        checked={form.can_view_all_ranking_groups}
                        disabled={!form.can_access_ranking}
                        onCheckedChange={(checked) =>
                            setForm((current) => ({
                                ...current,
                                can_view_all_ranking_groups: checked === true,
                            }))
                        }
                    />
                    <div className="space-y-1">
                        <Label htmlFor={`${idPrefix}-view-all`}>
                            {t('group_permission_view_all_rankings')}
                        </Label>
                        <p className="text-xs text-muted-foreground">
                            {t('group_permission_view_all_rankings_help')}
                        </p>
                    </div>
                </div>
            </div>
        );
    }

    return (
        <AdminSectionLayout
            title={t('groups_overview')}
            description={t('groups_overview_description')}
        >
            <Head title={t('admin_groups')} />

            <StatusBanner message={message} error={errorMessage} />

            <div className="flex justify-end">
                <Dialog
                    open={isCreateOpen}
                    onOpenChange={(isOpen) => {
                        setIsCreateOpen(isOpen);
                        if (!isOpen) {
                            setErrors({});
                            setCreateForm(emptyForm(defaultColor));
                        }
                    }}
                >
                    <DialogTrigger asChild>
                        <Button type="button">{t('create_group')}</Button>
                    </DialogTrigger>
                    <DialogContent className="max-h-[85vh] overflow-y-auto sm:max-w-lg">
                        <DialogHeader>
                            <DialogTitle>{t('create_group')}</DialogTitle>
                            <DialogDescription>{t('create_group_description')}</DialogDescription>
                        </DialogHeader>
                        <form onSubmit={(event) => void handleCreate(event)} className="space-y-4">
                            <div className="space-y-1">
                                <Label htmlFor="create-group-name">{t('group_name')}</Label>
                                <Input
                                    id="create-group-name"
                                    value={createForm.name}
                                    onChange={(event) =>
                                        setCreateForm((current) => ({
                                            ...current,
                                            name: event.target.value,
                                        }))
                                    }
                                    required
                                />
                                <InputError message={firstError('name')} />
                            </div>
                            <div className="space-y-2">
                                <Label>{t('group_color')}</Label>
                                {renderColorPicker(
                                    createForm.color,
                                    (color) =>
                                        setCreateForm((current) => ({ ...current, color })),
                                    'create-color',
                                )}
                                <InputError message={firstError('color')} />
                            </div>
                            {renderPermissionFields(createForm, setCreateForm, 'create')}
                            <div className="flex justify-end">
                                <Button type="submit" disabled={isCreating}>
                                    {isCreating ? t('creating') : t('create_group')}
                                </Button>
                            </div>
                        </form>
                    </DialogContent>
                </Dialog>
            </div>

            <Dialog
                open={editingGroup !== null}
                onOpenChange={(isOpen) => {
                    if (!isOpen) {
                        setEditingGroup(null);
                        setErrors({});
                    }
                }}
            >
                <DialogContent className="max-h-[85vh] overflow-y-auto sm:max-w-lg">
                    <DialogHeader>
                        <DialogTitle>{t('edit_group')}</DialogTitle>
                        <DialogDescription>{t('edit_group_description')}</DialogDescription>
                    </DialogHeader>
                    <form onSubmit={(event) => void handleUpdate(event)} className="space-y-4">
                        <div className="space-y-1">
                            <Label htmlFor="edit-group-name">{t('group_name')}</Label>
                            <Input
                                id="edit-group-name"
                                value={editForm.name}
                                onChange={(event) =>
                                    setEditForm((current) => ({
                                        ...current,
                                        name: event.target.value,
                                    }))
                                }
                                required
                            />
                            <InputError message={firstError('name')} />
                        </div>
                        <div className="space-y-2">
                            <Label>{t('group_color')}</Label>
                            {renderColorPicker(
                                editForm.color,
                                (color) => setEditForm((current) => ({ ...current, color })),
                                'edit-color',
                            )}
                            <InputError message={firstError('color')} />
                        </div>
                        {renderPermissionFields(editForm, setEditForm, 'edit')}
                        <div className="flex justify-end">
                            <Button type="submit" disabled={isSaving}>
                                {isSaving ? t('saving') : t('save')}
                            </Button>
                        </div>
                    </form>
                </DialogContent>
            </Dialog>

            {groups.length === 0 ? (
                <p className="text-sm text-muted-foreground">{t('no_groups_yet')}</p>
            ) : (
                <div className="space-y-3">
                    {groups.map((group) => (
                        <div
                            key={group.id}
                            className="flex flex-col gap-4 rounded-2xl border border-border/70 bg-card p-4 shadow-sm sm:flex-row sm:items-center sm:justify-between"
                        >
                            <div className="space-y-2">
                                <div className="flex items-center gap-2">
                                    <span
                                        className="inline-block h-3 w-3 rounded-full"
                                        style={{ backgroundColor: group.color_hex }}
                                    />
                                    <p className="text-base font-semibold tracking-tight">
                                        {group.name}
                                    </p>
                                </div>
                                <p className="text-sm text-muted-foreground">
                                    {t('group_members_count')}: {group.users_count}
                                </p>
                                <div className="flex flex-wrap gap-2 text-xs">
                                    {group.can_access_ranking ? (
                                        <span className="rounded-md border border-emerald-200 bg-emerald-50 px-2 py-0.5 text-emerald-700 dark:border-emerald-900/60 dark:bg-emerald-950/30 dark:text-emerald-300">
                                            {t('group_permission_access_ranking')}
                                        </span>
                                    ) : null}
                                    {group.can_view_all_ranking_groups ? (
                                        <span className="rounded-md border border-blue-200 bg-blue-50 px-2 py-0.5 text-blue-700 dark:border-blue-900/60 dark:bg-blue-950/30 dark:text-blue-300">
                                            {t('group_permission_view_all_rankings')}
                                        </span>
                                    ) : null}
                                </div>
                            </div>
                            <div className="flex flex-wrap gap-2">
                                <Button
                                    type="button"
                                    variant="outline"
                                    onClick={() => openEdit(group)}
                                >
                                    {t('edit')}
                                </Button>
                                <Button
                                    type="button"
                                    variant="destructive"
                                    disabled={deletingGroupId === group.id}
                                    onClick={() => void handleDelete(group)}
                                >
                                    {deletingGroupId === group.id ? t('deleting') : t('delete')}
                                </Button>
                            </div>
                        </div>
                    ))}
                </div>
            )}
        </AdminSectionLayout>
    );
}
