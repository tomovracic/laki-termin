import type { BracketPreviewMatch, KnockoutParticipantDraft } from '@/components/league/types';

export function nextPowerOfTwo(n: number): number {
    let power = 1;

    while (power < n) {
        power *= 2;
    }

    return power;
}

export function standardSeedSlots(size: number): number[] {
    let slots = [1];

    for (let currentSize = 1; currentSize < size; currentSize *= 2) {
        const next: number[] = [];

        for (const seed of slots) {
            next.push(seed);
            next.push(currentSize * 2 + 1 - seed);
        }

        slots = next;
    }

    return slots;
}

/**
 * First-round seeds (1-based) or null for empty slots.
 * Standard placement keeps all byes in round 1 so later rounds stay full.
 */
export function firstRoundSeedSlots(
    participantCount: number,
    bracketSize: number,
): Array<number | null> {
    return standardSeedSlots(bracketSize).map((seed) =>
        seed <= participantCount ? seed : null,
    );
}

export function buildBracketPreview(participants: KnockoutParticipantDraft[]): BracketPreviewMatch[] {
    if (participants.length < 2) {
        return [];
    }

    const bracketSize = nextPowerOfTwo(participants.length);
    const seedSlots = firstRoundSeedSlots(participants.length, bracketSize);
    const firstRoundMatchCount = bracketSize / 2;
    const matches: BracketPreviewMatch[] = [];

    for (let position = 0; position < firstRoundMatchCount; position++) {
        const leftSeed = seedSlots[position * 2];
        const rightSeed = seedSlots[position * 2 + 1];
        const left = leftSeed !== null ? participants[leftSeed - 1] : null;
        const right = rightSeed !== null ? participants[rightSeed - 1] : null;
        const hasLeft = left !== null;
        const hasRight = right !== null;

        matches.push({
            round: 1,
            position,
            player_one: left?.display_name ?? null,
            player_two: right?.display_name ?? null,
            is_bye: hasLeft !== hasRight,
            is_empty: !hasLeft && !hasRight,
        });
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
