export type QualificationSummary = {
    groupCount: number;
    qualifyPerGroup: 1 | 2;
    bestRunnersUp: number;
    knockoutSlots: number;
    minPlayersPerGroup: number;
    isUneven: boolean;
    isValid: boolean;
    errorKey: string | null;
};

export function groupLetters(count: number): string[] {
    return Array.from({ length: count }, (_, index) =>
        String.fromCharCode(65 + index),
    );
}

export function distributeSnake(
    playerCount: number,
    groupCount: number,
): number[][] {
    const groups: number[][] = Array.from({ length: groupCount }, () => []);

    if (groupCount < 1 || playerCount < 1) {
        return groups;
    }

    let groupIndex = 0;
    let direction = 1;

    for (let playerIndex = 0; playerIndex < playerCount; playerIndex++) {
        groups[groupIndex]?.push(playerIndex);

        const nextIndex = groupIndex + direction;

        if (nextIndex < 0 || nextIndex >= groupCount) {
            direction *= -1;
        } else {
            groupIndex = nextIndex;
        }
    }

    return groups;
}

export function summarizeQualification(
    groupSizes: number[],
    qualifyPerGroup: 1 | 2,
    bestRunnersUp: number,
): QualificationSummary {
    const groupCount = groupSizes.length;
    const knockoutSlots = groupCount * qualifyPerGroup + bestRunnersUp;
    const minPlayersPerGroup = Math.max(
        2,
        qualifyPerGroup + (bestRunnersUp > 0 ? 1 : 0),
    );
    const isUneven = groupSizes.some((size) => size !== groupSizes[0]);
    const tooSmall = groupSizes.some((size) => size < minPlayersPerGroup);
    const leftoverRank = qualifyPerGroup + 1;
    const leftoverAvailable = groupSizes.filter(
        (size) => size >= leftoverRank,
    ).length;

    let errorKey: string | null = null;

    if (groupCount < 2) {
        errorKey = 'tournament_groups_min';
    } else if (tooSmall) {
        errorKey = 'tournament_groups_too_small';
    } else if (bestRunnersUp > groupCount) {
        errorKey = 'tournament_best_runners_too_many';
    } else if (bestRunnersUp > leftoverAvailable) {
        errorKey = 'tournament_best_runners_too_many';
    } else if (knockoutSlots < 2) {
        errorKey = 'tournament_knockout_min_players';
    }

    return {
        groupCount,
        qualifyPerGroup,
        bestRunnersUp,
        knockoutSlots,
        minPlayersPerGroup,
        isUneven,
        isValid: errorKey === null,
        errorKey,
    };
}

export function groupPairings(playerNames: string[]): Array<[string, string]> {
    const pairs: Array<[string, string]> = [];

    for (let i = 0; i < playerNames.length; i++) {
        for (let j = i + 1; j < playerNames.length; j++) {
            const first = playerNames[i];
            const second = playerNames[j];

            if (first === undefined || second === undefined) {
                continue;
            }

            pairs.push([first, second]);
        }
    }

    return pairs;
}
