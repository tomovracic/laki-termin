import { Trash2 } from 'lucide-react';
import { useState } from 'react';
import type { InactivePeriod } from '@/components/admin/types';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { useI18n } from '@/lib/i18n';

type InactivePeriodsListProps = {
    periods: InactivePeriod[];
    deletingPeriodId: number | null;
    onDelete: (period: InactivePeriod) => void;
};

function reasonLabel(
    reason: InactivePeriod['reason'],
    t: (key: string) => string,
): string {
    if (reason === 'rain') {
        return t('inactive_reason_rain');
    }

    if (reason === 'maintenance') {
        return t('inactive_reason_maintenance');
    }

    return t('inactive_reason_other');
}

function formatDateRange(
    fromDate: string,
    toDate: string,
    locale: string,
): string {
    const formatter = new Intl.DateTimeFormat(locale, {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
    });
    const from = formatter.format(new Date(`${fromDate}T12:00:00`));

    if (fromDate === toDate) {
        return from;
    }

    const to = formatter.format(new Date(`${toDate}T12:00:00`));

    return `${from} – ${to}`;
}

function isUpcoming(period: InactivePeriod): boolean {
    const today = new Date();
    today.setHours(0, 0, 0, 0);
    const toDate = new Date(`${period.to_date}T23:59:59`);

    return toDate.getTime() >= today.getTime();
}

export function InactivePeriodsList({
    periods,
    deletingPeriodId,
    onDelete,
}: InactivePeriodsListProps) {
    const { locale, t } = useI18n();
    const [deleteTarget, setDeleteTarget] = useState<InactivePeriod | null>(null);

    if (periods.length === 0) {
        return (
            <p className="text-sm text-muted-foreground">{t('no_blocked_days')}</p>
        );
    }

    const sortedPeriods = [...periods].sort((left, right) =>
        left.from_date.localeCompare(right.from_date),
    );

    return (
        <>
            <div className="space-y-3">
                {sortedPeriods.map((period) => (
                    <div
                        key={period.id}
                        className="flex flex-col gap-3 rounded-lg border p-3 sm:flex-row sm:items-center sm:justify-between"
                    >
                        <div className="space-y-1">
                            <div className="flex flex-wrap items-center gap-2">
                                <p className="font-medium">
                                    {formatDateRange(
                                        period.from_date,
                                        period.to_date,
                                        locale,
                                    )}
                                </p>
                                <Badge variant={isUpcoming(period) ? 'default' : 'secondary'}>
                                    {isUpcoming(period)
                                        ? t('blocked_day_upcoming')
                                        : t('blocked_day_past')}
                                </Badge>
                            </div>
                            <p className="text-sm text-muted-foreground">
                                {period.terrain_name ?? t('all_terrains')}
                                {' · '}
                                {reasonLabel(period.reason, t)}
                            </p>
                            {period.note !== null && period.note.trim() !== '' && (
                                <p className="text-sm">{period.note}</p>
                            )}
                        </div>

                        <Button
                            type="button"
                            variant="outline"
                            size="sm"
                            className="shrink-0"
                            disabled={deletingPeriodId === period.id}
                            onClick={() => setDeleteTarget(period)}
                        >
                            <Trash2 className="size-4" />
                            {t('remove')}
                        </Button>
                    </div>
                ))}
            </div>

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
                        <DialogTitle>{t('delete_blocked_day')}</DialogTitle>
                        <DialogDescription>
                            {t('confirm_delete_blocked_day')}
                        </DialogDescription>
                    </DialogHeader>
                    <DialogFooter>
                        <Button
                            type="button"
                            variant="outline"
                            onClick={() => setDeleteTarget(null)}
                        >
                            {t('cancel')}
                        </Button>
                        <Button
                            type="button"
                            variant="destructive"
                            disabled={deleteTarget === null}
                            onClick={() => {
                                if (deleteTarget === null) {
                                    return;
                                }

                                onDelete(deleteTarget);
                                setDeleteTarget(null);
                            }}
                        >
                            {t('delete_blocked_day')}
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </>
    );
}
