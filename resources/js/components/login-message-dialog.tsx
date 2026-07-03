import { usePage } from '@inertiajs/react';
import { useEffect, useState } from 'react';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { useI18n } from '@/lib/i18n';
import type { SharedNavProps } from '@/types/nav';

export function LoginMessageDialog() {
    const { loginMessage, nav } = usePage().props;
    const sharedNav = nav as SharedNavProps | null | undefined;
    const mustAcknowledge = sharedNav?.must_acknowledge_terrain_usage_rules ?? false;
    const { t } = useI18n();
    const [open, setOpen] = useState(false);

    useEffect(() => {
        if (
            !mustAcknowledge
            && typeof loginMessage === 'string'
            && loginMessage.trim() !== ''
        ) {
            setOpen(true);
        }
    }, [loginMessage, mustAcknowledge]);

    if (typeof loginMessage !== 'string' || loginMessage.trim() === '') {
        return null;
    }

    return (
        <Dialog open={open} onOpenChange={setOpen}>
            <DialogContent className="sm:max-w-lg">
                <DialogHeader>
                    <DialogTitle>{t('login_message_title')}</DialogTitle>
                    <DialogDescription>{t('login_message_dialog_description')}</DialogDescription>
                </DialogHeader>
                <p className="whitespace-pre-wrap text-sm">{loginMessage}</p>
                <DialogFooter>
                    <Button type="button" onClick={() => setOpen(false)}>
                        {t('login_message_dismiss')}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}
