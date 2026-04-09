export type ManagedUser = {
    id: number;
    first_name: string;
    last_name: string;
    name: string;
    email: string;
    phone: string | null;
    token_count: number;
    invitation_status: 'pending' | 'active';
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

export type AdminUserReservation = {
    id: number;
    status: string | null;
    display_status: 'pending' | 'cancelled' | 'played';
    reserved_for_date?: string | null;
    reserved_from_time?: string | null;
    reserved_to_time?: string | null;
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
