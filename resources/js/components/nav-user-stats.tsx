import { usePage } from '@inertiajs/react';
import { Coins, ScrollText } from 'lucide-react';
import { useState } from 'react';
import { TerrainUsageRulesDialog } from '@/components/terrain-usage-rules-dialog';
import { Button } from '@/components/ui/button';
import { useI18n } from '@/lib/i18n';
import { useNavTokenCount } from '@/lib/nav-token-count';
import type { SharedNavProps } from '@/types/nav';

export function NavUserStats() {
    const { props } = usePage();
    const nav = props.nav as SharedNavProps | null | undefined;
    const { t } = useI18n();
    const [isRulesDialogOpen, setIsRulesDialogOpen] = useState(false);
    const tokenCount = useNavTokenCount(nav?.token_count ?? 0);

    if (nav === null || nav === undefined) {
        return null;
    }

    const tokenUnitLabel =
        tokenCount === 1 ? t('token_unit_singular') : t('token_unit_plural');
    const hasRules = nav.terrain_usage_rules.length > 0;

    return (
        <>
            <div className="flex items-center gap-2">
                <div className="flex items-center gap-2 rounded-lg border border-border/60 bg-muted/30 px-3 py-1.5">
                    <div className="flex size-7 shrink-0 items-center justify-center rounded-md bg-primary/8 text-primary ring-1 ring-primary/15 dark:bg-primary/15">
                        <Coins className="size-3.5" />
                    </div>
                    <div className="flex items-baseline gap-1 whitespace-nowrap">
                        <span className="text-lg font-bold tabular-nums leading-none text-foreground">
                            {tokenCount}
                        </span>
                        <span className="text-xs font-medium text-muted-foreground">
                            {tokenUnitLabel}
                        </span>
                    </div>
                </div>

                {hasRules && (
                    <Button
                        type="button"
                        variant="outline"
                        size="sm"
                        onClick={() => setIsRulesDialogOpen(true)}
                        className="h-9 gap-1.5 rounded-lg border-border/70 px-2.5 text-xs font-medium sm:px-3 sm:text-sm"
                    >
                        <ScrollText className="size-3.5 shrink-0 text-muted-foreground sm:size-4" />
                        <span className="hidden sm:inline">
                            {t('terrain_usage_rules_open')}
                        </span>
                        <span className="sm:hidden">{t('rules_short')}</span>
                    </Button>
                )}
            </div>

            {hasRules && (
                <TerrainUsageRulesDialog
                    open={isRulesDialogOpen}
                    onOpenChange={setIsRulesDialogOpen}
                    rules={nav.terrain_usage_rules}
                />
            )}
        </>
    );
}
