import { Button } from '@/components/ui/button';
import { useI18n } from '@/lib/i18n';

type PaginationControlsProps = {
    page: number;
    totalPages: number;
    onPageChange: (page: number) => void;
};

export function PaginationControls({
    page,
    totalPages,
    onPageChange,
}: PaginationControlsProps) {
    const { t } = useI18n();

    if (totalPages <= 1) {
        return null;
    }

    return (
        <div className="flex items-center justify-end gap-2">
            <Button
                variant="outline"
                size="sm"
                disabled={page <= 1}
                onClick={() => onPageChange(page - 1)}
            >
                {t('previous')}
            </Button>
            <span className="text-sm text-muted-foreground">
                {t('page_of')
                    .replace('{page}', `${page}`)
                    .replace('{totalPages}', `${totalPages}`)}
            </span>
            <Button
                variant="outline"
                size="sm"
                disabled={page >= totalPages}
                onClick={() => onPageChange(page + 1)}
            >
                {t('next')}
            </Button>
        </div>
    );
}
