import { router, usePage } from '@inertiajs/react';
import { useState } from 'react';
import { TerrainUsageRulesDialog } from '@/components/terrain-usage-rules-dialog';
import type { SharedNavProps } from '@/types/nav';

export function TerrainUsageRulesAcknowledgementDialog() {
    const { props } = usePage();
    const nav = props.nav as SharedNavProps | null | undefined;
    const mustAcknowledge = nav?.must_acknowledge_terrain_usage_rules ?? false;
    const rules = nav?.terrain_usage_rules ?? [];
    const [open, setOpen] = useState(mustAcknowledge);
    const [isSubmitting, setIsSubmitting] = useState(false);

    if (!mustAcknowledge || rules.length === 0) {
        return null;
    }

    function handleConfirm(): void {
        setIsSubmitting(true);

        router.post(
            '/terrain-usage-rules/acknowledge',
            {},
            {
                preserveScroll: true,
                onSuccess: () => {
                    setOpen(false);
                    setIsSubmitting(false);
                },
                onError: () => {
                    setIsSubmitting(false);
                },
            },
        );
    }

    return (
        <TerrainUsageRulesDialog
            open={open}
            onOpenChange={setOpen}
            rules={rules}
            required
            isSubmitting={isSubmitting}
            onConfirm={handleConfirm}
        />
    );
}
