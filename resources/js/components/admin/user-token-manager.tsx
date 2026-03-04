import type { ManagedUser } from '@/components/admin/types';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useI18n } from '@/lib/i18n';

type UserTokenManagerProps = {
    users: ManagedUser[];
    tokenDrafts: Record<number, string>;
    savingUserId: number | null;
    onDraftChange: (userId: number, value: string) => void;
    onSave: (user: ManagedUser) => void;
};

export function UserTokenManager({
    users,
    tokenDrafts,
    savingUserId,
    onDraftChange,
    onSave,
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
                    className="grid gap-4 rounded-xl border border-border/70 bg-card p-4 md:grid-cols-[minmax(0,1fr)_260px] md:items-start"
                >
                    <div className="space-y-2">
                        <p className="text-base font-semibold tracking-tight">
                            {user.first_name} {user.last_name}
                        </p>
                        <dl className="grid gap-1 text-sm text-muted-foreground">
                            <div className="flex flex-wrap items-center gap-1">
                                <dt className="font-medium text-foreground/80">
                                    {t('email_address')}:
                                </dt>
                                <dd>{user.email}</dd>
                            </div>
                            <div className="flex flex-wrap items-center gap-1">
                                <dt className="font-medium text-foreground/80">
                                    {t('phone_number')}:
                                </dt>
                                <dd>{user.phone ?? '-'}</dd>
                            </div>
                        </dl>
                    </div>
                    <div className="grid gap-2 sm:grid-cols-[160px_auto] sm:items-end md:border-l md:pl-4">
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
                    </div>
                </div>
            ))}
        </div>
    );
}
