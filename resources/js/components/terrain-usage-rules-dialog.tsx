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
    required?: boolean;
    isSubmitting?: boolean;
    onConfirm?: () => void;
};

export function TerrainUsageRulesDialog({
    open,
    onOpenChange,
    rules,
    required = false,
    isSubmitting = false,
    onConfirm,
}: TerrainUsageRulesDialogProps) {
    const { t } = useI18n();

    function handleOpenChange(nextOpen: boolean): void {
        if (required && !nextOpen) {
            return;
        }

        onOpenChange(nextOpen);
    }

    function handleConfirm(): void {
        if (onConfirm !== undefined) {
            onConfirm();
            return;
        }

        onOpenChange(false);
    }

    return (
        <Dialog open={open} onOpenChange={handleOpenChange}>
            <DialogContent
                className="sm:max-w-lg"
                showCloseButton={!required}
                onInteractOutside={(event) => {
                    if (required) {
                        event.preventDefault();
                    }
                }}
                onEscapeKeyDown={(event) => {
                    if (required) {
                        event.preventDefault();
                    }
                }}
            >
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
                    <Button
                        type="button"
                        onClick={handleConfirm}
                        disabled={isSubmitting}
                    >
                        {t('terrain_usage_rules_dismiss')}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}
