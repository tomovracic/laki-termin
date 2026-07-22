import type { ReactNode } from 'react';
import { useEffect } from 'react';
import { ChartNoAxesColumnIncreasing, Globe2 } from 'lucide-react';
import { Checkbox } from '@/components/ui/checkbox';
import { cn } from '@/lib/utils';

type MatchOptionTogglesProps = {
    isPublic: boolean;
    isRanked: boolean;
    onPublicChange: (value: boolean) => void;
    onRankedChange: (value: boolean) => void;
    idPrefix?: string;
};

type OptionRowProps = {
    id: string;
    checked: boolean;
    title: string;
    description: string;
    icon: ReactNode;
    disabled?: boolean;
    onCheckedChange: (value: boolean) => void;
};

function OptionRow({
    id,
    checked,
    title,
    description,
    icon,
    disabled = false,
    onCheckedChange,
}: OptionRowProps) {
    return (
        <label
            htmlFor={id}
            className={cn(
                'group flex items-start gap-3 rounded-xl border px-3.5 py-3 transition-colors',
                disabled
                    ? 'cursor-not-allowed border-border/60 bg-muted/10 opacity-60'
                    : 'cursor-pointer border-border bg-background hover:bg-accent/40',
            )}
        >
            <span
                className={cn(
                    'mt-0.5 flex size-9 shrink-0 items-center justify-center rounded-lg transition-colors',
                    disabled
                        ? 'bg-muted text-muted-foreground'
                        : 'bg-primary/10 text-primary',
                )}
            >
                {icon}
            </span>

            <span className="min-w-0 flex-1">
                <span className="block text-sm font-medium leading-5">{title}</span>
                <span className="mt-0.5 block text-xs leading-4 text-muted-foreground">
                    {description}
                </span>
            </span>

            <Checkbox
                id={id}
                checked={checked}
                disabled={disabled}
                onCheckedChange={(value) => onCheckedChange(value === true)}
                className="mt-1 size-5 rounded-md"
            />
        </label>
    );
}

export function MatchOptionToggles({
    isPublic,
    isRanked,
    onPublicChange,
    onRankedChange,
    idPrefix = 'played-match',
}: MatchOptionTogglesProps) {
    function handlePublicChange(value: boolean) {
        onPublicChange(value);

        if (!value) {
            onRankedChange(false);
        }
    }

    useEffect(() => {
        if (!isPublic && isRanked) {
            onRankedChange(false);
        }
    }, [isPublic, isRanked, onRankedChange]);

    return (
        <div className="space-y-2.5">
            <OptionRow
                id={`${idPrefix}-is-public`}
                checked={isPublic}
                title="Javni meč"
                description="Vidljiv svim igračima u povijesti mečeva"
                icon={<Globe2 className="size-4" />}
                onCheckedChange={handlePublicChange}
            />
            <OptionRow
                id={`${idPrefix}-is-ranked`}
                checked={isRanked}
                disabled={!isPublic}
                title="Rangirani meč"
                description={
                    isPublic
                        ? 'Ulazi u ELO rangiranje'
                        : 'Dostupno samo za javne mečeve'
                }
                icon={<ChartNoAxesColumnIncreasing className="size-4" />}
                onCheckedChange={onRankedChange}
            />
        </div>
    );
}
