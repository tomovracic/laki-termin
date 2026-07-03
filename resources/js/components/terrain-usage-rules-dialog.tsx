import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Icon } from '@/components/ui/icon';
import { useI18n } from '@/lib/i18n';
import {
    terrainUsageRuleIconComponent,
    type TerrainUsageRule,
} from '@/lib/terrain-usage-rule-icons';
import { Button } from '@/components/ui/button';

type TerrainUsageRulesDialogProps = {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    rules: TerrainUsageRule[];
};

export function TerrainUsageRulesDialog({
    open,
    onOpenChange,
    rules,
}: TerrainUsageRulesDialogProps) {
    const { t } = useI18n();

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="sm:max-w-lg">
                <DialogHeader>
                    <DialogTitle>{t('terrain_usage_rules_title')}</DialogTitle>
                    <DialogDescription>
                        {t('terrain_usage_rules_dialog_description')}
                    </DialogDescription>
                </DialogHeader>
                <ul className="space-y-3">
                    {rules.map((rule, index) => (
                        <li
                            key={`${rule.icon}-${index}`}
                            className="flex gap-3 rounded-lg border border-border/70 bg-muted/30 p-3"
                        >
                            <Icon
                                iconNode={terrainUsageRuleIconComponent(rule.icon)}
                                className="mt-0.5 size-5 shrink-0 text-primary"
                            />
                            <p className="text-sm leading-relaxed">{rule.text}</p>
                        </li>
                    ))}
                </ul>
                <DialogFooter>
                    <Button type="button" onClick={() => onOpenChange(false)}>
                        {t('terrain_usage_rules_dismiss')}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}
