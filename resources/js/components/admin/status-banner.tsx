type StatusBannerProps = {
    message: string | null;
    error: string | null;
};

export function StatusBanner({ message, error }: StatusBannerProps) {
    if (message === null && error === null) {
        return null;
    }

    if (error !== null) {
        return (
            <div className="rounded-lg border border-red-500/30 bg-red-500/10 px-4 py-2 text-sm text-red-700 dark:text-red-300">
                {error}
            </div>
        );
    }

    return (
        <div className="rounded-lg border border-emerald-500/30 bg-emerald-500/10 px-4 py-2 text-sm text-emerald-700 dark:text-emerald-300">
            {message}
        </div>
    );
}
