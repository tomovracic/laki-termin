import { Plus, RefreshCcw, Trash2 } from 'lucide-react';
import type { FormEvent } from 'react';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { cn } from '@/lib/utils';
import { useI18n } from '@/lib/i18n';
import {
    TERRAIN_USAGE_RULE_ICONS,
    terrainUsageRuleIconComponent,
    type TerrainUsageRule,
    type TerrainUsageRuleIconName,
} from '@/lib/terrain-usage-rule-icons';

type TerrainUsageRulesFormProps = {
    value: TerrainUsageRule[];
    isSaving: boolean;
    error?: string;
    onChange: (rules: TerrainUsageRule[]) => void;
    onSubmit: (event: FormEvent<HTMLFormElement>) => void;
};

function createEmptyRule(): TerrainUsageRule {
    return {
        icon: 'info',
        text: '',
    };
}

export function TerrainUsageRulesForm({
    value,
    isSaving,
    error,
    onChange,
    onSubmit,
}: TerrainUsageRulesFormProps) {
    const { t } = useI18n();

    function updateRule(index: number, patch: Partial<TerrainUsageRule>): void {
        onChange(
            value.map((rule, ruleIndex) =>
                ruleIndex === index ? { ...rule, ...patch } : rule,
            ),
        );
    }

    function removeRule(index: number): void {
        onChange(value.filter((_, ruleIndex) => ruleIndex !== index));
    }

    function addRule(): void {
        onChange([...value, createEmptyRule()]);
    }

    return (
        <form className="space-y-4 rounded-xl border p-4" onSubmit={onSubmit}>
            <div className="space-y-1">
                <h2 className="text-lg font-semibold">
                    {t('terrain_usage_rules_settings_title')}
                </h2>
                <p className="text-sm text-muted-foreground">
                    {t('terrain_usage_rules_settings_description')}
                </p>
            </div>

            <div className="space-y-4">
                {value.length === 0 && (
                    <p className="text-sm text-muted-foreground">
                        {t('terrain_usage_rules_empty')}
                    </p>
                )}

                {value.map((rule, index) => (
                    <div
                        key={`rule-${index}`}
                        className="space-y-3 rounded-lg border border-border/70 p-3"
                    >
                        <div className="flex items-center justify-between gap-2">
                            <Label>
                                {t('terrain_usage_rule_number').replace(
                                    '{number}',
                                    `${index + 1}`,
                                )}
                            </Label>
                            <Button
                                type="button"
                                variant="ghost"
                                size="icon"
                                onClick={() => removeRule(index)}
                                aria-label={t('remove_terrain_usage_rule')}
                            >
                                <Trash2 className="size-4" />
                            </Button>
                        </div>

                        <div className="space-y-2">
                            <Label>{t('terrain_usage_rule_icon')}</Label>
                            <div className="flex flex-wrap gap-2">
                                {TERRAIN_USAGE_RULE_ICONS.map((iconName) => {
                                    const IconComponent =
                                        terrainUsageRuleIconComponent(iconName);

                                    return (
                                        <button
                                            key={iconName}
                                            type="button"
                                            title={t(
                                                `terrain_usage_rule_icon_${iconName}` as 'terrain_usage_rule_icon_clock',
                                            )}
                                            onClick={() =>
                                                updateRule(index, {
                                                    icon: iconName as TerrainUsageRuleIconName,
                                                })
                                            }
                                            className={cn(
                                                'inline-flex size-9 items-center justify-center rounded-md border transition-colors',
                                                rule.icon === iconName
                                                    ? 'border-primary bg-primary/10 text-primary'
                                                    : 'border-border bg-background text-muted-foreground hover:bg-muted',
                                            )}
                                        >
                                            <IconComponent className="size-4" />
                                        </button>
                                    );
                                })}
                            </div>
                        </div>

                        <div className="space-y-2">
                            <Label htmlFor={`terrain-usage-rule-text-${index}`}>
                                {t('terrain_usage_rule_text')}
                            </Label>
                            <Input
                                id={`terrain-usage-rule-text-${index}`}
                                value={rule.text}
                                onChange={(event) =>
                                    updateRule(index, { text: event.target.value })
                                }
                                placeholder={t('terrain_usage_rule_text_placeholder')}
                            />
                        </div>
                    </div>
                ))}
            </div>

            <Button type="button" variant="outline" onClick={addRule}>
                <Plus className="size-4" />
                {t('add_terrain_usage_rule')}
            </Button>

            {error !== undefined && <p className="text-sm text-red-500">{error}</p>}

            <Button type="submit" disabled={isSaving}>
                {isSaving ? (
                    <>
                        <RefreshCcw className="size-4 animate-spin" />
                        {t('saving')}
                    </>
                ) : (
                    t('save_terrain_usage_rules')
                )}
            </Button>
        </form>
    );
}
