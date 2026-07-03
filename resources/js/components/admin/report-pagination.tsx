import { router } from '@inertiajs/react';
import { PaginationControls } from '@/components/admin/pagination-controls';
import type { PaginatedMeta } from '@/components/admin/report-types';

type ReportPaginationProps = {
    meta?: PaginatedMeta;
    routePath: string;
    query: Record<string, string | number | null | undefined>;
};

export function ReportPagination({ meta, routePath, query }: ReportPaginationProps) {
    const currentPage = meta?.current_page ?? 1;
    const totalPages = meta?.last_page ?? 1;

    function handlePageChange(page: number) {
        const params = Object.fromEntries(
            Object.entries({ ...query, page })
                .filter(([, value]) => value !== null && value !== undefined && value !== '')
                .map(([key, value]) => [key, String(value)]),
        );

        router.get(routePath, params, {
            preserveState: true,
            preserveScroll: true,
        });
    }

    return <PaginationControls page={currentPage} totalPages={totalPages} onPageChange={handlePageChange} />;
}
