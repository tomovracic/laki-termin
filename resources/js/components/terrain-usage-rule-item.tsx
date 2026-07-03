import { Icon } from '@/components/ui/icon';
import { useI18n } from '@/lib/i18n';
import {
    terrainUsageRuleEmphasisContainerClasses,
    terrainUsageRuleEmphasisIconClasses,
    terrainUsageRuleIconComponent,
    type TerrainUsageRule,
} from '@/lib/terrain-usage-rule-icons';
import { cn } from '@/lib/utils';

type TerrainUsageRuleItemProps = {
    rule: TerrainUsageRule;
    as?: 'li' | 'div';
    showPlaceholder?: boolean;
};

export function TerrainUsageRuleItem({
    rule,
    as: Component = 'li',
    showPlaceholder = false,
}: TerrainUsageRuleItemProps) {
    const { t } = useI18n();
    const trimmedText = rule.text.trim();
    const displayText =
        trimmedText !== ''
            ? trimmedText
            : showPlaceholder
              ? t('terrain_usage_rule_preview_placeholder')
              : '';

    if (displayText === '') {
        return null;
    }

    return (
        <Component
            className={cn(
                'flex gap-3 rounded-lg p-3',
                terrainUsageRuleEmphasisContainerClasses(rule.emphasis),
            )}
        >
            <Icon
                iconNode={terrainUsageRuleIconComponent(rule.icon)}
                className={cn(
                    'mt-0.5 size-5 shrink-0',
                    terrainUsageRuleEmphasisIconClasses(rule.emphasis),
                )}
            />
            <p
                className={cn(
                    'text-sm leading-relaxed',
                    rule.emphasis != null && 'font-medium',
                    showPlaceholder && trimmedText === '' && 'text-muted-foreground italic',
                )}
            >
                {displayText}
            </p>
        </Component>
    );
}
