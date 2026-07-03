export type ReportFilterUser = {
    id: number;
    first_name: string;
    last_name: string;
    name: string;
    email: string;
};

export type ReportFilterTerrain = {
    id: number;
    name: string;
    code: string;
};

export type PaginatedMeta = {
    current_page?: number;
    last_page?: number;
    total?: number;
};

export type PaginatedResponse<T> = {
    data: T[];
    meta?: PaginatedMeta;
    links?: {
        first?: string | null;
        last?: string | null;
        prev?: string | null;
        next?: string | null;
    };
};

export type LoginReportEntry = {
    id: number;
    user_id: number;
    logged_in_at: string;
    ip_address: string | null;
    user: ReportFilterUser | null;
};

export type ReportReservation = {
    id: number;
    user_id: number;
    status: string | null;
    display_status: 'pending' | 'cancelled' | 'played';
    reserved_for_date?: string | null;
    reserved_from_time?: string | null;
    reserved_to_time?: string | null;
    cancelled_at?: string | null;
    cancel_reason?: string | null;
    user: ReportFilterUser | null;
    slot: {
        id: number;
        starts_at?: string;
        ends_at?: string;
        status: string;
        terrain: {
            id: number;
            name: string;
            code: string;
        } | null;
    } | null;
};
