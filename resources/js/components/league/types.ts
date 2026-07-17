export type LeagueFormat = 'round_robin' | 'knockout';

export type LeagueSummary = {
    id: number;
    name: string;
    format?: LeagueFormat;
    rounds: number;
    sets_best_of?: number;
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
    user_id: number | null;
    name: string;
    first_name: string;
    last_name: string;
    seed?: number | null;
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
    id: number | null;
    name: string;
    first_name: string;
    last_name: string;
    avatar?: string | null;
};

export type LeagueMatch = {
    id: number;
    round: number;
    bracket_round?: number | null;
    bracket_position?: number | null;
    next_match_id?: number | null;
    next_match_slot?: number | null;
    is_bye?: boolean;
    is_empty?: boolean;
    status: 'pending' | 'played';
    player_one: LeagueMatchPlayer | null;
    player_two: LeagueMatchPlayer | null;
    set1_player_one_games: number | null;
    set1_player_two_games: number | null;
    set2_player_one_games: number | null;
    set2_player_two_games: number | null;
    set3_player_one_games: number | null;
    set3_player_two_games: number | null;
    set4_player_one_games?: number | null;
    set4_player_two_games?: number | null;
    set5_player_one_games?: number | null;
    set5_player_two_games?: number | null;
    played_at?: string | null;
};

export type LeagueDetail = {
    id: number;
    name: string;
    format?: LeagueFormat;
    rounds: number;
    sets_best_of?: number;
    participants_count: number;
    matches_count: number;
    played_matches_count: number;
};

export type LeagueMatchResultPayload = {
    set1_player_one_games: number;
    set1_player_two_games: number;
    set2_player_one_games?: number | null;
    set2_player_two_games?: number | null;
    set3_player_one_games?: number | null;
    set3_player_two_games?: number | null;
    set4_player_one_games?: number | null;
    set4_player_two_games?: number | null;
    set5_player_one_games?: number | null;
    set5_player_two_games?: number | null;
};

export type KnockoutParticipantDraft = {
    key: string;
    user_id: number | null;
    first_name: string;
    last_name: string;
    display_name: string;
};

export type BracketPreviewMatch = {
    round: number;
    position: number;
    player_one: string | null;
    player_two: string | null;
    is_bye: boolean;
    is_empty: boolean;
};
