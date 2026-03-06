import { useEffect } from 'react';
import { toast } from 'sonner';

type StatusBannerProps = {
    message: string | null;
    error: string | null;
};

export function StatusBanner({ message, error }: StatusBannerProps) {
    useEffect(() => {
        if (error !== null) {
            toast.error(error, { id: `status-error-${error}` });
        }
    }, [error]);

    useEffect(() => {
        if (message !== null) {
            toast.success(message, { id: `status-success-${message}` });
        }
    }, [message]);

    return null;
}
