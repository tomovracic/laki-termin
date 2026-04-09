import type { ManagedUser } from '@/components/admin/types';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useI18n } from '@/lib/i18n';

type UserTokenManagerProps = {
    users: ManagedUser[];
    tokenDrafts: Record<number, string>;
    savingUserId: number | null;
    showTokenControls?: boolean;
    onDraftChange: (userId: number, value: string) => void;
    onSave: (user: ManagedUser) => void;
    onOpenReservations?: (user: ManagedUser) => void;
};

export function UserTokenManager({
    users,
    tokenDrafts,
    savingUserId,
    showTokenControls = true,
    onDraftChange,
    onSave,
    onOpenReservations,
}: UserTokenManagerProps) {
    const { t } = useI18n();

    return (
        <div className="space-y-4">
            {users.length === 0 && (
                <p className="text-sm text-muted-foreground">
                    {t('no_users_match_filter')}
                </p>
            )}
            {users.map((user) => (
                <div
                    key={user.id}
                    className="grid gap-4 rounded-2xl border border-border/70 bg-card p-4 shadow-sm md:grid-cols-[1fr_auto]"
                >
                    <div className="space-y-3">
                        {showTokenControls ? (
                            <>
                                <p className="text-base font-semibold tracking-tight">
                                    {user.first_name} {user.last_name}
                                </p>

                                <dl className="grid gap-2 text-sm text-muted-foreground sm:grid-cols-[140px_1fr]">
                                    <dt className="font-medium text-foreground/80">
                                        {t('email_address')}:
                                    </dt>
                                    <dd className="break-all">{user.email}</dd>

                                    <dt className="font-medium text-foreground/80">
                                        {t('phone_number')}:
                                    </dt>
                                    <dd>{user.phone ?? '-'}</dd>

                                    <dt className="font-medium text-foreground/80">
                                        {t('available_tokens')}:
                                    </dt>
                                    <dd>{tokenDrafts[user.id] ?? '0'}</dd>
                                </dl>
                            </>
                        ) : (
                            <div className="space-y-2 text-sm">
                                <p className="text-base font-semibold tracking-tight">
                                    {user.first_name} {user.last_name}
                                </p>
                                <div className="flex flex-wrap items-center gap-1 text-muted-foreground">
                                    <span className="font-medium text-foreground/80">
                                        {t('email_address')}:
                                    </span>
                                    <span className="break-all">{user.email}</span>
                                </div>
                            </div>
                        )}
                    </div>
                    {showTokenControls && (
                        <div className="grid gap-3 rounded-xl border border-border/60 bg-muted/30 p-3 sm:grid-cols-[170px_auto] sm:items-end">
                            <div className="space-y-1">
                                <Label htmlFor={`token-count-${user.id}`}>
                                    {t('available_tokens')}
                                </Label>
                                <Input
                                    id={`token-count-${user.id}`}
                                    min={0}
                                    type="number"
                                    value={tokenDrafts[user.id] ?? '0'}
                                    onChange={(event) =>
                                        onDraftChange(user.id, event.target.value)
                                    }
                                />
                            </div>
                            <Button
                                className="w-full sm:w-auto"
                                onClick={() => onSave(user)}
                                disabled={savingUserId === user.id}
                            >
                                {savingUserId === user.id ? t('saving') : t('update')}
                            </Button>
                            {onOpenReservations !== undefined && (
                                <Button
                                    className="w-full sm:w-auto"
                                    variant="outline"
                                    onClick={() => onOpenReservations(user)}
                                >
                                    {`${t('view_reservations')} (${user.reservations_count})`}
                                </Button>
                            )}
                        </div>
                    )}
                </div>
            ))}
        </div>
    );
}
