export type ManagedUser = {
    id: number;
    first_name: string;
    last_name: string;
    name: string;
    email: string;
    phone: string | null;
    token_count: number;
    reservations_count: number;
    created_at: string | null;
};

export type ManagedTerrain = {
    id: number;
    name: string;
    code: string;
    description: string | null;
    is_active: boolean;
    created_at: string | null;
};

export type GlobalSetting = {
    max_advance_days: number;
    cancellation_cutoff_hours: number;
    availability_periods: {
        from: string;
        to: string;
        slot_duration_minutes: number;
    }[];
};

export type ApiErrorResponse = {
    message?: string;
    errors?: Record<string, string[]>;
};
