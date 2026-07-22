export type EloRankingEntry = {
    user_id: number;
    first_name: string;
    last_name: string;
    name: string;
    elo: number;
    matches_played: number;
    wins: number;
    losses: number;
};

export type EloRankingGroupSection = {
    id: number;
    name: string;
    color: string;
    color_hex: string;
    rankings: EloRankingEntry[];
};
