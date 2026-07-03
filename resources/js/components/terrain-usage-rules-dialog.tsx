import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { TerrainUsageRuleItem } from '@/components/terrain-usage-rule-item';
import { useI18n } from '@/lib/i18n';
import type { TerrainUsageRule } from '@/lib/terrain-usage-rule-icons';
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
                        <TerrainUsageRuleItem
                            key={`${rule.icon}-${index}`}
                            rule={rule}
                        />
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
