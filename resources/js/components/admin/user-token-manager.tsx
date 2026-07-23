import { Check, Copy, Eye, Pencil } from 'lucide-react';
import { useEffect, useState } from 'react';
import type { ManagedUser, ManagedUserGroup } from '@/components/admin/types';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import {
    Tooltip,
    TooltipContent,
    TooltipTrigger,
} from '@/components/ui/tooltip';
import { useClipboard } from '@/hooks/use-clipboard';
import { useI18n } from '@/lib/i18n';
import { cn } from '@/lib/utils';

type UserTokenManagerProps = {
    users: ManagedUser[];
    tokenDrafts: Record<number, string>;
    savingUserId: number | null;
    showTokenControls?: boolean;
    onDraftChange: (userId: number, value: string) => void;
    onSave: (user: ManagedUser) => void;
    onOpenReservations?: (user: ManagedUser) => void;
    onEditGroups?: (user: ManagedUser) => void;
};

function GroupBadges({ groups }: { groups: ManagedUserGroup[] }) {
    if (groups.length === 0) {
        return <span className="text-muted-foreground">—</span>;
    }

    return (
        <div className="flex flex-wrap gap-1.5">
            {groups.map((group) => (
                <span
                    key={group.id}
                    className="inline-flex items-center gap-1.5 rounded-md border px-2 py-0.5 text-xs"
                >
                    <span
                        className="inline-block size-2 rounded-full"
                        style={{ backgroundColor: group.color_hex }}
                    />
                    {group.name}
                </span>
            ))}
        </div>
    );
}

function PhoneCopyControl({ phone }: { phone: string }) {
    const { t } = useI18n();
    const [copiedText, copy] = useClipboard();
    const [justCopied, setJustCopied] = useState(false);
    const isCopied = justCopied && copiedText === phone;

    useEffect(() => {
        if (!isCopied) {
            return;
        }

        const timeoutId = window.setTimeout(() => {
            setJustCopied(false);
        }, 2000);

        return () => window.clearTimeout(timeoutId);
    }, [isCopied]);

    async function handleCopy(): Promise<void> {
        const success = await copy(phone);
        if (success) {
            setJustCopied(true);
        }
    }

    return (
        <div className="inline-flex items-center gap-1">
            <span className="whitespace-nowrap">{phone}</span>
            <Tooltip>
                <TooltipTrigger asChild>
                    <Button
                        type="button"
                        variant="ghost"
                        size="icon"
                        className={cn(
                            'size-7 shrink-0 text-muted-foreground hover:text-foreground',
                            isCopied && 'text-emerald-600 hover:text-emerald-600',
                        )}
                        aria-label={isCopied ? t('phone_number_copied') : t('copy_phone_number')}
                        onClick={() => void handleCopy()}
                    >
                        {isCopied ? <Check className="size-3.5" /> : <Copy className="size-3.5" />}
                    </Button>
                </TooltipTrigger>
                <TooltipContent>
                    {isCopied ? t('phone_number_copied') : t('copy_phone_number')}
                </TooltipContent>
            </Tooltip>
        </div>
    );
}

function GroupsField({
    user,
    onEditGroups,
}: {
    user: ManagedUser;
    onEditGroups?: (user: ManagedUser) => void;
}) {
    const { t } = useI18n();

    return (
        <div className="flex items-start gap-1">
            <div className="min-w-0 flex-1">
                <GroupBadges groups={user.groups ?? []} />
            </div>
            {onEditGroups !== undefined && (
                <Tooltip>
                    <TooltipTrigger asChild>
                        <Button
                            type="button"
                            variant="ghost"
                            size="icon"
                            className="size-7 shrink-0 text-muted-foreground hover:text-foreground"
                            aria-label={t('edit_user_groups')}
                            onClick={() => onEditGroups(user)}
                        >
                            <Pencil className="size-3.5" />
                        </Button>
                    </TooltipTrigger>
                    <TooltipContent>{t('edit_user_groups')}</TooltipContent>
                </Tooltip>
            )}
        </div>
    );
}

function TokenControls({
    user,
    tokenDraft,
    isSaving,
    onDraftChange,
    onSave,
}: {
    user: ManagedUser;
    tokenDraft: string;
    isSaving: boolean;
    onDraftChange: (userId: number, value: string) => void;
    onSave: (user: ManagedUser) => void;
}) {
    const { t } = useI18n();

    return (
        <div className="flex min-w-0 flex-1 items-center gap-2">
            <Input
                id={`token-count-${user.id}`}
                min={0}
                type="number"
                className="h-8 w-24"
                value={tokenDraft}
                onChange={(event) => onDraftChange(user.id, event.target.value)}
                aria-label={t('available_tokens')}
            />
            <Button size="sm" onClick={() => onSave(user)} disabled={isSaving}>
                {isSaving ? t('saving') : t('update')}
            </Button>
        </div>
    );
}

function ReservationsField({
    user,
    onOpenReservations,
}: {
    user: ManagedUser;
    onOpenReservations?: (user: ManagedUser) => void;
}) {
    const { t } = useI18n();

    if (onOpenReservations === undefined) {
        return (
            <span className="tabular-nums text-muted-foreground">{user.reservations_count}</span>
        );
    }

    return (
        <div className="flex items-center gap-1.5">
            <span className="tabular-nums text-muted-foreground">{user.reservations_count}</span>
            <Tooltip>
                <TooltipTrigger asChild>
                    <Button
                        type="button"
                        variant="ghost"
                        size="icon"
                        className="size-7 text-muted-foreground hover:text-foreground"
                        aria-label={t('view_reservations')}
                        onClick={() => onOpenReservations(user)}
                    >
                        <Eye className="size-4" />
                    </Button>
                </TooltipTrigger>
                <TooltipContent>{t('view_reservations')}</TooltipContent>
            </Tooltip>
        </div>
    );
}

export function UserTokenManager({
    users,
    tokenDrafts,
    savingUserId,
    showTokenControls = true,
    onDraftChange,
    onSave,
    onOpenReservations,
    onEditGroups,
}: UserTokenManagerProps) {
    const { t } = useI18n();

    if (users.length === 0) {
        return (
            <p className="text-sm text-muted-foreground">{t('no_users_match_filter')}</p>
        );
    }

    return (
        <>
            <ul className="space-y-3 md:hidden">
                {users.map((user) => (
                    <li
                        key={user.id}
                        className="space-y-3 rounded-lg border border-border/70 bg-card p-4"
                    >
                        <div className="space-y-1">
                            <p className="font-medium">
                                {user.first_name} {user.last_name}
                            </p>
                            <p className="break-all text-sm text-muted-foreground">{user.email}</p>
                        </div>

                        {showTokenControls && (
                            <div className="space-y-1">
                                <p className="text-xs font-medium text-muted-foreground">
                                    {t('phone_number')}
                                </p>
                                {user.phone ? (
                                    <PhoneCopyControl phone={user.phone} />
                                ) : (
                                    <span className="text-sm text-muted-foreground">—</span>
                                )}
                            </div>
                        )}

                        <div className="space-y-1">
                            <p className="text-xs font-medium text-muted-foreground">
                                {t('user_groups')}
                            </p>
                            <GroupsField user={user} onEditGroups={onEditGroups} />
                        </div>

                        {showTokenControls && (
                            <div className="space-y-1">
                                <p className="text-xs font-medium text-muted-foreground">
                                    {t('available_tokens')}
                                </p>
                                <TokenControls
                                    user={user}
                                    tokenDraft={tokenDrafts[user.id] ?? '0'}
                                    isSaving={savingUserId === user.id}
                                    onDraftChange={onDraftChange}
                                    onSave={onSave}
                                />
                            </div>
                        )}

                        {showTokenControls && (
                            <div className="flex items-center justify-between gap-3 border-t pt-3">
                                <p className="text-xs font-medium text-muted-foreground">
                                    {t('reservations')}
                                </p>
                                <ReservationsField
                                    user={user}
                                    onOpenReservations={onOpenReservations}
                                />
                            </div>
                        )}
                    </li>
                ))}
            </ul>

            <div className="hidden overflow-x-auto rounded-lg border md:block">
                <table
                    className={cn(
                        'w-full text-sm',
                        showTokenControls ? 'min-w-[980px]' : 'min-w-[560px]',
                    )}
                >
                    <thead className="bg-muted/50">
                        <tr>
                            <th className="px-4 py-3 text-left font-medium">{t('full_name')}</th>
                            <th className="px-4 py-3 text-left font-medium">{t('email_address')}</th>
                            {showTokenControls && (
                                <th className="px-4 py-3 text-left font-medium">{t('phone_number')}</th>
                            )}
                            <th className="px-4 py-3 text-left font-medium">{t('user_groups')}</th>
                            {showTokenControls && (
                                <th className="px-4 py-3 text-left font-medium">
                                    {t('available_tokens')}
                                </th>
                            )}
                            {showTokenControls && (
                                <th className="px-4 py-3 text-left font-medium">{t('reservations')}</th>
                            )}
                        </tr>
                    </thead>
                    <tbody>
                        {users.map((user) => (
                            <tr key={user.id} className="border-t">
                                <td className="px-4 py-3 font-medium whitespace-nowrap">
                                    {user.first_name} {user.last_name}
                                </td>
                                <td className="px-4 py-3 text-muted-foreground">
                                    <span className="break-all">{user.email}</span>
                                </td>
                                {showTokenControls && (
                                    <td className="px-4 py-3">
                                        {user.phone ? (
                                            <PhoneCopyControl phone={user.phone} />
                                        ) : (
                                            <span className="text-muted-foreground">—</span>
                                        )}
                                    </td>
                                )}
                                <td className="px-4 py-3">
                                    <GroupsField user={user} onEditGroups={onEditGroups} />
                                </td>
                                {showTokenControls && (
                                    <td className="px-4 py-3">
                                        <div className="min-w-[180px]">
                                            <TokenControls
                                                user={user}
                                                tokenDraft={tokenDrafts[user.id] ?? '0'}
                                                isSaving={savingUserId === user.id}
                                                onDraftChange={onDraftChange}
                                                onSave={onSave}
                                            />
                                        </div>
                                    </td>
                                )}
                                {showTokenControls && (
                                    <td className="px-4 py-3">
                                        <ReservationsField
                                            user={user}
                                            onOpenReservations={onOpenReservations}
                                        />
                                    </td>
                                )}
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>
        </>
    );
}
