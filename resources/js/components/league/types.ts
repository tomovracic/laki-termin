export type LeagueSummary = {
    id: number;
    name: string;
    rounds: number;
    participants_count: number;
    matches_count: number;
    played_matches_count: number;
    created_at?: string | null;
};

export type LeagueUserOption = {
    id: number;
    name: string;
    first_name: string;
    last_name: string;
    email: string;
};

export type LeagueParticipant = {
    id: number;
    user_id: number;
    name: string;
    first_name: string;
    last_name: string;
};

export type LeagueStandingsEntry = {
    user_id: number;
    first_name: string;
    last_name: string;
    name: string;
    matches_played: number;
    wins: number;
    losses: number;
    sets_won: number;
    sets_lost: number;
    set_difference: number;
};

export type LeagueMatchPlayer = {
    id: number;
    name: string;
    first_name: string;
    last_name: string;
    avatar?: string | null;
};

export type LeagueMatch = {
    id: number;
    round: number;
    status: 'pending' | 'played';
    player_one: LeagueMatchPlayer;
    player_two: LeagueMatchPlayer;
    set1_player_one_games: number | null;
    set1_player_two_games: number | null;
    set2_player_one_games: number | null;
    set2_player_two_games: number | null;
    set3_player_one_games: number | null;
    set3_player_two_games: number | null;
    played_at?: string | null;
};

export type LeagueDetail = {
    id: number;
    name: string;
    rounds: number;
    participants_count: number;
    matches_count: number;
    played_matches_count: number;
};

export type LeagueMatchResultPayload = {
    set1_player_one_games: number;
    set1_player_two_games: number;
    set2_player_one_games: number;
    set2_player_two_games: number;
    set3_player_one_games?: number | null;
    set3_player_two_games?: number | null;
};
