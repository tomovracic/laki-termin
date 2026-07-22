export type MatchHistoryPlayer = {
    user_id: number | null;
    name: string;
    avatar?: string | null;
};

export type MatchHistoryLeagueInfo = {
    id: number;
    name: string;
    round: number;
    sets_best_of?: 1 | 3 | 5;
};

export type MatchHistoryEntry = {
    id: string;
    source: 'casual' | 'league';
    played_at: string | null;
    player_one: MatchHistoryPlayer;
    player_two: MatchHistoryPlayer;
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
    league: MatchHistoryLeagueInfo | null;
    can_edit: boolean;
    can_delete: boolean;
};

export type UserSearchResult = {
    id: number;
    first_name: string;
    last_name: string;
    name: string;
    email: string;
};

export type MatchHistoryPlayerInput = {
    user_id: number | null;
    first_name: string;
    last_name: string;
    display_name: string;
};

export type CreatePlayedMatchPayload = {
    player_two: {
        user_id?: number | null;
        first_name?: string;
        last_name?: string;
    };
    'played_at'?: string;
    set1_player_one_games: number;
    set1_player_two_games: number;
    set2_player_one_games: number;
    set2_player_two_games: number;
    set3_player_one_games?: number | null;
    set3_player_two_games?: number | null;
};

export type UpdatePlayedMatchPayload = {
    set1_player_one_games: number;
    set1_player_two_games: number;
    set2_player_one_games?: number;
    set2_player_two_games?: number;
    set3_player_one_games?: number | null;
    set3_player_two_games?: number | null;
    set4_player_one_games?: number | null;
    set4_player_two_games?: number | null;
    set5_player_one_games?: number | null;
    set5_player_two_games?: number | null;
};

export function casualMatchIdToNumericId(id: string): string {
    return id.replace(/^casual-/, '');
}

export function leagueMatchIdToNumericId(id: string): string {
    return id.replace(/^league-/, '');
}

export function resolveMatchHistoryBestOf(match: MatchHistoryEntry): 1 | 3 | 5 {
    const bestOf = match.league?.sets_best_of;

    if (bestOf === 1 || bestOf === 5) {
        return bestOf;
    }

    return 3;
}
