export type LeagueFormat = 'round_robin' | 'knockout' | 'group_knockout';

export type KnockoutDrawMode = 'random' | 'seeded';

export type LeagueParticipantMode = 'singles' | 'doubles';

export type LeagueStage = 'group' | 'knockout';

export type TournamentKind = 'knockout' | 'group_knockout';

export type LeagueSummary = {
    id: number;
    name: string;
    format?: LeagueFormat;
    participant_mode?: LeagueParticipantMode;
    rounds: number;
    sets_best_of?: number;
    knockout_draw_mode?: KnockoutDrawMode | null;
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
    partner_user_id?: number | null;
    league_group_id?: number | null;
    name: string;
    first_name: string;
    last_name: string;
    seed?: number | null;
    received_bye?: boolean;
};

export type LeagueStandingsEntry = {
    participant_id: number;
    user_id: number | null;
    first_name: string;
    last_name: string;
    name: string;
    matches_played: number;
    wins: number;
    losses: number;
    sets_won: number;
    sets_lost: number;
    set_difference: number;
    games_won?: number;
    games_lost?: number;
    game_difference?: number;
    group_id?: number | null;
    group_name?: string | null;
    rank_in_group?: number | null;
};

export type LeagueMatchPlayer = {
    id: number | null;
    participant_id?: number | null;
    partner_id?: number | null;
    name: string;
    first_name: string;
    last_name: string;
    avatar?: string | null;
};

export type LeagueMatch = {
    id: number;
    round: number;
    league_group_id?: number | null;
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
    participant_mode?: LeagueParticipantMode;
    rounds: number;
    sets_best_of?: number;
    knockout_draw_mode?: KnockoutDrawMode | null;
    qualify_per_group?: number | null;
    best_runners_up?: number | null;
    current_stage?: LeagueStage | null;
    participants_count: number;
    matches_count: number;
    played_matches_count: number;
    current_bracket_round?: number | null;
    next_round_pending?: boolean;
    can_finish_round?: boolean;
    can_start_knockout?: boolean;
    group_stage_complete?: boolean;
};

export type KnockoutChampion = {
    id: number | null;
    user_id: number | null;
    participant_id?: number | null;
    name: string;
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

export type LeagueGroupSummary = {
    id: number;
    name: string;
    sort_order: number;
    standings: LeagueStandingsEntry[];
};

export type TournamentCreateParticipant = {
    user_id?: number | null;
    first_name?: string | null;
    last_name?: string | null;
};

export type TournamentCreateGroup = {
    name: string;
    participant_indexes: number[];
};

export type BracketPreviewMatch = {
    round: number;
    position: number;
    player_one: string | null;
    player_two: string | null;
    is_bye: boolean;
    is_empty: boolean;
};
