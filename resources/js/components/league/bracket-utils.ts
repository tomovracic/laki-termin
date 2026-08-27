import type {
    BracketPreviewMatch,
    KnockoutDrawMode,
    KnockoutParticipantDraft,
} from '@/components/league/types';

export type KnockoutRoundNameKey =
    | 'tournament_final'
    | 'tournament_final_three'
    | 'tournament_semifinal'
    | 'tournament_quarterfinal'
    | 'tournament_round_of_16'
    | 'tournament_round_of_32'
    | 'tournament_round_of_64';

type KnockoutRoundMatchLike = {
    is_bye?: boolean;
    is_empty?: boolean;
    player_one?:
        | { id?: number | null; participant_id?: number | null }
        | string
        | null;
    player_two?:
        | { id?: number | null; participant_id?: number | null }
        | string
        | null;
};

function uniquePlayerCount(matches: KnockoutRoundMatchLike[]): number {
    const ids = new Set<string>();

    for (const match of matches) {
        const players = [match.player_one, match.player_two];

        for (const [index, player] of players.entries()) {
            if (index === 1 && match.is_bye) {
                continue;
            }

            if (typeof player === 'string') {
                const name = player.trim();

                if (name !== '' && name !== '?') {
                    ids.add(`name:${name}`);
                }

                continue;
            }

            if (player?.participant_id != null) {
                ids.add(`p:${player.participant_id}`);
                continue;
            }

            if (player?.id != null) {
                ids.add(`u:${player.id}`);
            }
        }
    }

    return ids.size;
}

export function knockoutRoundNameKey(
    matches: KnockoutRoundMatchLike[],
): KnockoutRoundNameKey | null {
    if (matches.length === 0) {
        return null;
    }

    const competitive = matches.filter(
        (match) => !match.is_bye && !match.is_empty,
    );

    if (competitive.length === 3 && uniquePlayerCount(matches) === 3) {
        return 'tournament_final_three';
    }

    const slots = matches.reduce(
        (sum, match) => sum + (match.is_bye ? 1 : 2),
        0,
    );

    if (slots <= 2) {
        return 'tournament_final';
    }

    if (slots <= 4) {
        return 'tournament_semifinal';
    }

    if (slots <= 8) {
        return 'tournament_quarterfinal';
    }

    if (slots <= 16) {
        return 'tournament_round_of_16';
    }

    if (slots <= 32) {
        return 'tournament_round_of_32';
    }

    if (slots <= 64) {
        return 'tournament_round_of_64';
    }

    return null;
}

/**
 * Preview of the first knockout round only: at most one bye when count is odd
 * (except three players → round-robin of three matches).
 */
export function buildBracketPreview(
    participants: KnockoutParticipantDraft[],
    drawMode: KnockoutDrawMode = 'seeded',
): BracketPreviewMatch[] {
    if (participants.length < 2) {
        return [];
    }

    if (participants.length === 3) {
        return [
            {
                round: 1,
                position: 0,
                player_one: participants[0]?.display_name ?? null,
                player_two: participants[1]?.display_name ?? null,
                is_bye: false,
                is_empty: false,
            },
            {
                round: 1,
                position: 1,
                player_one: participants[0]?.display_name ?? null,
                player_two: participants[2]?.display_name ?? null,
                is_bye: false,
                is_empty: false,
            },
            {
                round: 1,
                position: 2,
                player_one: participants[1]?.display_name ?? null,
                player_two: participants[2]?.display_name ?? null,
                is_bye: false,
                is_empty: false,
            },
        ];
    }

    if (participants.length === 2) {
        return [
            {
                round: 1,
                position: 0,
                player_one: participants[0]?.display_name ?? null,
                player_two: participants[1]?.display_name ?? null,
                is_bye: false,
                is_empty: false,
            },
        ];
    }

    const matches: BracketPreviewMatch[] = [];
    let remaining = [...participants];
    let position = 0;

    if (remaining.length % 2 === 1) {
        if (drawMode === 'seeded') {
            const byePlayer = remaining[0];
            remaining = remaining.slice(1);
            matches.push({
                round: 1,
                position,
                player_one: byePlayer?.display_name ?? null,
                player_two: null,
                is_bye: true,
                is_empty: false,
            });
        } else {
            matches.push({
                round: 1,
                position,
                player_one: '?',
                player_two: null,
                is_bye: true,
                is_empty: false,
            });
            // Preview only: drop last seat so pair count matches odd field size.
            remaining = remaining.slice(0, remaining.length - 1);
        }

        position++;
    }

    if (drawMode === 'seeded') {
        const half = remaining.length / 2;

        for (let i = 0; i < half; i++) {
            const left = remaining[i];
            const right = remaining[remaining.length - 1 - i];
            matches.push({
                round: 1,
                position: position + i,
                player_one: left?.display_name ?? null,
                player_two: right?.display_name ?? null,
                is_bye: false,
                is_empty: false,
            });
        }
    } else {
        for (let i = 0; i < remaining.length; i += 2) {
            matches.push({
                round: 1,
                position: position + i / 2,
                player_one: remaining[i]?.display_name ?? null,
                player_two: remaining[i + 1]?.display_name ?? null,
                is_bye: false,
                is_empty: false,
            });
        }
    }

    return matches;
}

export function shuffleParticipants<T>(items: T[]): T[] {
    const copy = [...items];

    for (let i = copy.length - 1; i > 0; i--) {
        const j = Math.floor(Math.random() * (i + 1));
        [copy[i], copy[j]] = [copy[j], copy[i]];
    }

    return copy;
}
