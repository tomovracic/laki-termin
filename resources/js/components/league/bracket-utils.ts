import type { BracketPreviewMatch, KnockoutDrawMode, KnockoutParticipantDraft } from '@/components/league/types';

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
