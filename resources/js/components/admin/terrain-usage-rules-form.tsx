import { Pencil, Plus, RefreshCcw, Trash2 } from 'lucide-react';
import { useState } from 'react';
import type { FormEvent } from 'react';
import { TerrainUsageRuleItem } from '@/components/terrain-usage-rule-item';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { cn } from '@/lib/utils';
import { useI18n } from '@/lib/i18n';
import {
    TERRAIN_USAGE_RULE_EMPHASIS_OPTIONS,
    TERRAIN_USAGE_RULE_ICONS,
    terrainUsageRuleEmphasisPreviewClasses,
    terrainUsageRuleIconComponent,
    type TerrainUsageRule,
    type TerrainUsageRuleEmphasis,
    type TerrainUsageRuleIconName,
} from '@/lib/terrain-usage-rule-icons';

type TerrainUsageRulesFormProps = {
    rules: TerrainUsageRule[];
    creatingRule: boolean;
    updatingRuleIndex: number | null;
    deletingRuleIndex: number | null;
    error?: string;
    onCreate: (rule: TerrainUsageRule) => Promise<void>;
    onUpdate: (index: number, rule: TerrainUsageRule) => Promise<void>;
    onDelete: (index: number) => Promise<void>;
};

type TerrainUsageRuleFieldsProps = {
    rule: TerrainUsageRule;
    idPrefix: string;
    onChange: (patch: Partial<TerrainUsageRule>) => void;
};

type RuleModalState =
    | { mode: 'add' }
    | { mode: 'edit'; index: number };

function createEmptyRule(): TerrainUsageRule {
    return {
        icon: 'info',
        text: '',
    };
}

function TerrainUsageRuleFields({
    rule,
    idPrefix,
    onChange,
}: TerrainUsageRuleFieldsProps) {
    const { t } = useI18n();

    return (
        <>
            <div className="space-y-2">
                <Label>{t('terrain_usage_rule_icon')}</Label>
                <div className="flex flex-wrap gap-2">
                    {TERRAIN_USAGE_RULE_ICONS.map((iconName) => {
                        const IconComponent = terrainUsageRuleIconComponent(iconName);

                        return (
                            <button
                                key={iconName}
                                type="button"
                                title={t(
                                    `terrain_usage_rule_icon_${iconName}` as 'terrain_usage_rule_icon_clock',
                                )}
                                onClick={() =>
                                    onChange({
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
                <Label htmlFor={`${idPrefix}-text`}>{t('terrain_usage_rule_text')}</Label>
                <Input
                    id={`${idPrefix}-text`}
                    value={rule.text}
                    onChange={(event) => onChange({ text: event.target.value })}
                    placeholder={t('terrain_usage_rule_text_placeholder')}
                />
            </div>

            <div className="space-y-3">
                <div className="flex items-center gap-2">
                    <Checkbox
                        id={`${idPrefix}-emphasized`}
                        checked={rule.emphasis != null}
                        onCheckedChange={(checked) =>
                            onChange({
                                emphasis: checked === true ? 'neutral' : null,
                            })
                        }
                    />
                    <Label
                        htmlFor={`${idPrefix}-emphasized`}
                        className="cursor-pointer font-normal"
                    >
                        {t('terrain_usage_rule_emphasized')}
                    </Label>
                </div>

                {rule.emphasis != null && (
                    <div className="space-y-2 pl-6">
                        <Label>{t('terrain_usage_rule_emphasis_color')}</Label>
                        <div className="flex flex-wrap gap-2">
                            {TERRAIN_USAGE_RULE_EMPHASIS_OPTIONS.map((emphasisOption) => (
                                <button
                                    key={emphasisOption}
                                    type="button"
                                    title={t(
                                        `terrain_usage_rule_emphasis_${emphasisOption}` as 'terrain_usage_rule_emphasis_neutral',
                                    )}
                                    onClick={() =>
                                        onChange({
                                            emphasis: emphasisOption as TerrainUsageRuleEmphasis,
                                        })
                                    }
                                    className={cn(
                                        'inline-flex h-9 items-center gap-2 rounded-md border px-3 text-sm transition-colors',
                                        rule.emphasis === emphasisOption
                                            ? 'border-primary bg-primary/10 text-primary'
                                            : 'border-border bg-background text-muted-foreground hover:bg-muted',
                                    )}
                                >
                                    <span
                                        className={cn(
                                            'size-3 rounded-full',
                                            terrainUsageRuleEmphasisPreviewClasses(
                                                emphasisOption,
                                            ),
                                        )}
                                    />
                                    {t(
                                        `terrain_usage_rule_emphasis_${emphasisOption}` as 'terrain_usage_rule_emphasis_neutral',
                                    )}
                                </button>
                            ))}
                        </div>
                    </div>
                )}
            </div>

            <div className="space-y-2 border-t border-border/70 pt-3">
                <Label>{t('terrain_usage_rule_preview')}</Label>
                <TerrainUsageRuleItem rule={rule} as="div" showPlaceholder />
            </div>
        </>
    );
}

export function TerrainUsageRulesForm({
    rules,
    creatingRule,
    updatingRuleIndex,
    deletingRuleIndex,
    error,
    onCreate,
    onUpdate,
    onDelete,
}: TerrainUsageRulesFormProps) {
    const { t } = useI18n();
    const [ruleModal, setRuleModal] = useState<RuleModalState | null>(null);
    const [draftRule, setDraftRule] = useState<TerrainUsageRule>(createEmptyRule);

    const isModalSaving =
        creatingRule ||
        (ruleModal?.mode === 'edit' && updatingRuleIndex === ruleModal.index);

    function openAddModal(): void {
        setDraftRule(createEmptyRule());
        setRuleModal({ mode: 'add' });
    }

    function openEditModal(index: number): void {
        setDraftRule({ ...rules[index] });
        setRuleModal({ mode: 'edit', index });
    }

    function closeModal(): void {
        setRuleModal(null);
    }

    async function handleModalSubmit(event: FormEvent<HTMLFormElement>): Promise<void> {
        event.preventDefault();

        if (draftRule.text.trim() === '' || ruleModal === null) {
            return;
        }

        try {
            if (ruleModal.mode === 'add') {
                await onCreate(draftRule);
            } else {
                await onUpdate(ruleModal.index, draftRule);
            }

            closeModal();
        } catch {
            // Keep modal open when save fails.
        }
    }

    return (
        <div className="space-y-4 rounded-xl border p-4">
            <div className="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                <div className="space-y-1">
                    <h2 className="text-lg font-semibold">
                        {t('terrain_usage_rules_settings_title')}
                    </h2>
                    <p className="text-sm text-muted-foreground">
                        {t('terrain_usage_rules_settings_description')}
                    </p>
                </div>
                <Button type="button" variant="outline" onClick={openAddModal}>
                    <Plus className="size-4" />
                    {t('add_terrain_usage_rule')}
                </Button>
            </div>

            <div className="space-y-3">
                {rules.length === 0 && (
                    <p className="text-sm text-muted-foreground">
                        {t('terrain_usage_rules_empty')}
                    </p>
                )}

                {rules.map((rule, index) => (
                    <TerrainUsageRuleItem
                        key={`rule-${index}`}
                        rule={rule}
                        as="div"
                        actions={(
                            <>
                                <Button
                                    type="button"
                                    variant="ghost"
                                    size="icon"
                                    className="size-8"
                                    onClick={() => openEditModal(index)}
                                    disabled={deletingRuleIndex === index}
                                    aria-label={t('edit')}
                                >
                                    <Pencil className="size-4" />
                                </Button>
                                <Button
                                    type="button"
                                    variant="ghost"
                                    size="icon"
                                    className="size-8"
                                    onClick={() => void onDelete(index)}
                                    disabled={deletingRuleIndex === index}
                                    aria-label={t('remove_terrain_usage_rule')}
                                >
                                    {deletingRuleIndex === index ? (
                                        <RefreshCcw className="size-4 animate-spin" />
                                    ) : (
                                        <Trash2 className="size-4" />
                                    )}
                                </Button>
                            </>
                        )}
                    />
                ))}
            </div>

            <Dialog
                open={ruleModal !== null}
                onOpenChange={(open) => {
                    if (!open && !isModalSaving) {
                        closeModal();
                    }
                }}
            >
                <DialogContent>
                    <form className="space-y-4" onSubmit={(event) => void handleModalSubmit(event)}>
                        <DialogHeader>
                            <DialogTitle>
                                {ruleModal?.mode === 'edit'
                                    ? t('edit_terrain_usage_rule')
                                    : t('add_terrain_usage_rule')}
                            </DialogTitle>
                            <DialogDescription>
                                {t('terrain_usage_rules_settings_description')}
                            </DialogDescription>
                        </DialogHeader>

                        <TerrainUsageRuleFields
                            rule={draftRule}
                            idPrefix="terrain-usage-rule-modal"
                            onChange={(patch) =>
                                setDraftRule((current) => ({ ...current, ...patch }))
                            }
                        />

                        {error !== undefined && (
                            <p className="text-sm text-red-500">{error}</p>
                        )}

                        <DialogFooter>
                            <Button
                                type="button"
                                variant="outline"
                                onClick={closeModal}
                                disabled={isModalSaving}
                            >
                                {t('cancel')}
                            </Button>
                            <Button type="submit" disabled={isModalSaving}>
                                {isModalSaving ? (
                                    <>
                                        <RefreshCcw className="size-4 animate-spin" />
                                        {t('saving')}
                                    </>
                                ) : ruleModal?.mode === 'edit' ? (
                                    t('save')
                                ) : (
                                    t('add_terrain_usage_rule')
                                )}
                            </Button>
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>
        </div>
    );
}
