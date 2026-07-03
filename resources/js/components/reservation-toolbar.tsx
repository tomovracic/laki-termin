import { CalendarDays, ChevronLeft, ChevronRight } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { useI18n } from '@/lib/i18n';
import { cn } from '@/lib/utils';

type ReservationToolbarProps = {
    dateLabel: string;
    isLoading: boolean;
    loadingLabel: string;
    isPreviousDisabled: boolean;
    isNextDisabled: boolean;
    onDateStep: (days: number) => void;
    className?: string;
};

export function ReservationToolbar({
    dateLabel,
    isLoading,
    loadingLabel,
    isPreviousDisabled,
    isNextDisabled,
    onDateStep,
    className,
}: ReservationToolbarProps) {
    const { t } = useI18n();

    return (
        <div
            className={cn(
                'inline-flex w-full max-w-md items-center gap-1 rounded-xl border border-border/60 bg-card p-2 shadow-sm',
                className,
            )}
        >
            <Button
                type="button"
                variant="ghost"
                size="icon"
                disabled={isPreviousDisabled}
                onClick={() => onDateStep(-1)}
                aria-label={t('previous')}
                className="size-9 shrink-0 rounded-lg text-muted-foreground hover:bg-muted/80 hover:text-foreground"
            >
                <ChevronLeft className="size-4" />
            </Button>

            <div className="flex min-w-0 flex-1 items-center gap-2.5 rounded-lg bg-muted/40 px-3 py-2">
                <div className="flex size-8 shrink-0 items-center justify-center rounded-md bg-background text-muted-foreground shadow-xs ring-1 ring-border/60">
                    <CalendarDays className="size-4" />
                </div>
                <div className="min-w-0 flex-1 text-left">
                    <p className="truncate text-sm font-semibold leading-tight text-foreground">
                        {isLoading ? loadingLabel : dateLabel}
                    </p>
                </div>
            </div>

            <Button
                type="button"
                variant="ghost"
                size="icon"
                disabled={isNextDisabled}
                onClick={() => onDateStep(1)}
                aria-label={t('next')}
                className="size-9 shrink-0 rounded-lg text-muted-foreground hover:bg-muted/80 hover:text-foreground"
            >
                <ChevronRight className="size-4" />
            </Button>
        </div>
    );
}
