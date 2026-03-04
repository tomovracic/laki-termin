import type { FormEvent, ReactNode } from 'react';
import { useEffect, useState } from 'react';
import type { ManagedTerrain } from '@/components/admin/types';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
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
import { useI18n } from '@/lib/i18n';

type TerrainOverviewProps = {
    activeTab: 'overview' | 'settings';
    terrains: ManagedTerrain[];
    descriptionDrafts: Record<number, string>;
    savingTerrainId: number | null;
    terrainName: string;
    terrainDescription: string;
    terrainErrors: Record<string, string[]>;
    isCreatingTerrain: boolean;
    settingsContent?: ReactNode;
    onDescriptionChange: (terrainId: number, value: string) => void;
    onSaveDescription: (terrain: ManagedTerrain) => void;
    onToggleStatus: (terrain: ManagedTerrain) => void;
    onTerrainNameChange: (value: string) => void;
    onTerrainDescriptionChange: (value: string) => void;
    onCreateTerrain: (event: FormEvent<HTMLFormElement>) => void;
};

export function TerrainOverview({
    activeTab,
    terrains,
    descriptionDrafts,
    savingTerrainId,
    terrainName,
    terrainDescription,
    terrainErrors,
    isCreatingTerrain,
    settingsContent,
    onDescriptionChange,
    onSaveDescription,
    onToggleStatus,
    onTerrainNameChange,
    onTerrainDescriptionChange,
    onCreateTerrain,
}: TerrainOverviewProps) {
    const { t } = useI18n();
    const [confirmTarget, setConfirmTarget] = useState<ManagedTerrain | null>(
        null,
    );
    const [isCreateDialogOpen, setIsCreateDialogOpen] = useState(false);
    const titleKey =
        activeTab === 'settings'
            ? 'global_reservation_settings'
            : 'terrain_management';
    const descriptionKey =
        activeTab === 'settings'
            ? 'global_reservation_settings_description'
            : 'terrain_management_description';

    useEffect(() => {
        if (
            !isCreatingTerrain &&
            terrainName === '' &&
            terrainDescription === '' &&
            Object.keys(terrainErrors).length === 0
        ) {
            setIsCreateDialogOpen(false);
        }
    }, [isCreatingTerrain, terrainDescription, terrainErrors, terrainName]);

    function handleToggleClick(terrain: ManagedTerrain): void {
        if (terrain.is_active) {
            setConfirmTarget(terrain);
            return;
        }

        onToggleStatus(terrain);
    }

    function confirmDeactivate(): void {
        if (confirmTarget === null) {
            return;
        }

        onToggleStatus(confirmTarget);
        setConfirmTarget(null);
    }

    return (
        <>
            <Card className="border-border/70">
                <CardHeader>
                    <CardTitle>{t(titleKey)}</CardTitle>
                    <CardDescription>{t(descriptionKey)}</CardDescription>
                </CardHeader>
                <CardContent className="space-y-4">
                    {activeTab === 'overview' ? (
                        <>
                            <div className="flex justify-end">
                                <Button
                                    type="button"
                                    onClick={() => setIsCreateDialogOpen(true)}
                                >
                                    {t('create_terrain')}
                                </Button>
                            </div>

                            <div className="grid gap-3 md:grid-cols-2 xl:grid-cols-3">
                                {terrains.length === 0 && (
                                    <p className="text-sm text-muted-foreground md:col-span-2 xl:col-span-3">
                                        {t('no_terrains_match_filter')}
                                    </p>
                                )}
                                {terrains.map((terrain) => (
                                    <div
                                        key={terrain.id}
                                        className="space-y-3 rounded-lg border p-3"
                                    >
                                        <div className="flex items-center justify-between gap-3">
                                            <div>
                                                <p className="text-lg font-semibold tracking-tight">
                                                    {terrain.name}
                                                </p>
                                                <p className="text-xs text-muted-foreground">
                                                    {t('code')}: {terrain.code}
                                                </p>
                                            </div>
                                            <Badge
                                                variant={
                                                    terrain.is_active
                                                        ? 'default'
                                                        : 'destructive'
                                                }
                                            >
                                                {terrain.is_active
                                                    ? t('active')
                                                    : t('inactive')}
                                            </Badge>
                                        </div>

                                        <Input
                                            value={
                                                descriptionDrafts[terrain.id] ??
                                                ''
                                            }
                                            onChange={(event) =>
                                                onDescriptionChange(
                                                    terrain.id,
                                                    event.target.value,
                                                )
                                            }
                                            placeholder={t('short_description')}
                                        />

                                        <div className="flex gap-2">
                                            <Button
                                                className="flex-1"
                                                variant="outline"
                                                onClick={() =>
                                                    onSaveDescription(terrain)
                                                }
                                                disabled={
                                                    savingTerrainId ===
                                                    terrain.id
                                                }
                                            >
                                                {t('save_text')}
                                            </Button>
                                            <Button
                                                className={
                                                    terrain.is_active
                                                        ? 'flex-1 border-amber-200 bg-amber-50 text-amber-900 hover:bg-amber-100 hover:text-amber-950 dark:border-amber-900/70 dark:bg-amber-950/40 dark:text-amber-200 dark:hover:bg-amber-950/60'
                                                        : 'flex-1'
                                                }
                                                variant={
                                                    terrain.is_active
                                                        ? 'outline'
                                                        : 'default'
                                                }
                                                onClick={() =>
                                                    handleToggleClick(terrain)
                                                }
                                                disabled={
                                                    savingTerrainId ===
                                                    terrain.id
                                                }
                                            >
                                                {terrain.is_active
                                                    ? t('deactivate')
                                                    : t('activate')}
                                            </Button>
                                        </div>
                                    </div>
                                ))}
                            </div>
                        </>
                    ) : (
                        <div>
                            {settingsContent ?? (
                                <p className="text-sm text-muted-foreground">
                                    {t('no_settings_available')}
                                </p>
                            )}
                        </div>
                    )}
                </CardContent>
            </Card>

            <Dialog
                open={isCreateDialogOpen}
                onOpenChange={setIsCreateDialogOpen}
            >
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>{t('create_terrain')}</DialogTitle>
                        <DialogDescription>
                            {t('create_terrain_description')}
                        </DialogDescription>
                    </DialogHeader>
                    <form className="space-y-4" onSubmit={onCreateTerrain}>
                        <div className="grid gap-2">
                            <Label htmlFor="terrain-name-modal">
                                {t('name')}
                            </Label>
                            <Input
                                id="terrain-name-modal"
                                required
                                value={terrainName}
                                onChange={(event) =>
                                    onTerrainNameChange(event.target.value)
                                }
                                placeholder={t('terrain_name_placeholder')}
                            />
                            {terrainErrors.name?.[0] !== undefined && (
                                <p className="text-sm text-red-500">
                                    {terrainErrors.name[0]}
                                </p>
                            )}
                        </div>

                        <div className="grid gap-2">
                            <Label htmlFor="terrain-description-modal">
                                {t('description')}
                            </Label>
                            <textarea
                                id="terrain-description-modal"
                                className="min-h-24 rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background placeholder:text-muted-foreground focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 focus-visible:outline-none"
                                value={terrainDescription}
                                onChange={(event) =>
                                    onTerrainDescriptionChange(
                                        event.target.value,
                                    )
                                }
                                placeholder={t('optional_details')}
                            />
                        </div>

                        <DialogFooter>
                            <Button
                                type="button"
                                variant="outline"
                                onClick={() => setIsCreateDialogOpen(false)}
                            >
                                {t('cancel')}
                            </Button>
                            <Button type="submit" disabled={isCreatingTerrain}>
                                {isCreatingTerrain
                                    ? t('creating')
                                    : t('create_terrain')}
                            </Button>
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>

            <Dialog
                open={confirmTarget !== null}
                onOpenChange={(open) => {
                    if (!open) {
                        setConfirmTarget(null);
                    }
                }}
            >
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>{t('deactivate_terrain')}</DialogTitle>
                        <DialogDescription>
                            {confirmTarget === null
                                ? t('confirm_deactivate_terrain')
                                : `${t('confirm_deactivate_named')} "${confirmTarget.name}"?`}
                        </DialogDescription>
                    </DialogHeader>
                    <DialogFooter>
                        <Button
                            variant="outline"
                            onClick={() => setConfirmTarget(null)}
                        >
                            {t('cancel')}
                        </Button>
                        <Button
                            variant="outline"
                            className="border-amber-200 bg-amber-50 text-amber-900 hover:bg-amber-100 hover:text-amber-950 dark:border-amber-900/70 dark:bg-amber-950/40 dark:text-amber-200 dark:hover:bg-amber-950/60"
                            onClick={confirmDeactivate}
                            disabled={confirmTarget === null}
                        >
                            {t('deactivate')}
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </>
    );
}
