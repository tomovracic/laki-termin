import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { useInitials } from '@/hooks/use-initials';
import { cn } from '@/lib/utils';

export type MatchDisplayPlayer = {
    userId: number | null;
    name: string;
    avatar?: string | null;
};

export type MatchSetScoreFields = {
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
};

type SetScore = {
    playerOneGames: number;
    playerTwoGames: number;
};

type MatchWinner = 'player_one' | 'player_two' | null;

const scoreBoxClassName =
    'flex h-9 w-9 shrink-0 items-center justify-center rounded-lg border bg-muted/50 text-sm tabular-nums md:h-10 md:w-10';

const playerRowClassName = 'flex h-9 items-center md:h-10';

export function getSetScores(match: MatchSetScoreFields): SetScore[] {
    const rawSets: Array<[number | null | undefined, number | null | undefined]> = [
        [match.set1_player_one_games, match.set1_player_two_games],
        [match.set2_player_one_games, match.set2_player_two_games],
        [match.set3_player_one_games, match.set3_player_two_games],
        [match.set4_player_one_games, match.set4_player_two_games],
        [match.set5_player_one_games, match.set5_player_two_games],
    ];

    return rawSets.flatMap(([playerOneGames, playerTwoGames]) => {
        if (
            playerOneGames === null ||
            playerOneGames === undefined ||
            playerTwoGames === null ||
            playerTwoGames === undefined
        ) {
            return [];
        }

        return [{ playerOneGames, playerTwoGames }];
    });
}

function getMatchWinner(sets: SetScore[]): MatchWinner {
    if (sets.length === 0) {
        return null;
    }

    let playerOneSetsWon = 0;
    let playerTwoSetsWon = 0;

    for (const set of sets) {
        if (set.playerOneGames > set.playerTwoGames) {
            playerOneSetsWon++;
        } else if (set.playerTwoGames > set.playerOneGames) {
            playerTwoSetsWon++;
        }
    }

    if (playerOneSetsWon === playerTwoSetsWon) {
        return null;
    }

    return playerOneSetsWon > playerTwoSetsWon ? 'player_one' : 'player_two';
}

function MatchPlayerRow({
    player,
    highlighted = false,
    isWinner = false,
}: {
    player: MatchDisplayPlayer;
    highlighted?: boolean;
    isWinner?: boolean;
}) {
    const getInitials = useInitials();

    return (
        <div className={cn(playerRowClassName, 'min-w-0 gap-2')}>
            <Avatar className="size-8 shrink-0">
                <AvatarImage src={player.avatar ?? undefined} alt={player.name} />
                <AvatarFallback className="bg-neutral-200 text-xs font-medium text-black dark:bg-neutral-700 dark:text-white">
                    {getInitials(player.name)}
                </AvatarFallback>
            </Avatar>
            <span
                className={cn(
                    'truncate font-medium',
                    highlighted && 'text-primary',
                    isWinner && 'font-bold',
                )}
            >
                {player.name}
            </span>
        </div>
    );
}

type MatchScoreboardProps = {
    playerOne: MatchDisplayPlayer;
    playerTwo: MatchDisplayPlayer;
    sets: SetScore[];
    highlightUserId?: number | null;
};

export function MatchScoreboard({
    playerOne,
    playerTwo,
    sets,
    highlightUserId,
}: MatchScoreboardProps) {
    const winner = getMatchWinner(sets);
    const playerOneIsWinner = winner === 'player_one';
    const playerTwoIsWinner = winner === 'player_two';

    return (
        <div className="flex items-start gap-3 sm:gap-4">
            <div className="flex min-w-0 flex-1 flex-col gap-1.5">
                <MatchPlayerRow
                    player={playerOne}
                    highlighted={highlightUserId === playerOne.userId}
                    isWinner={playerOneIsWinner}
                />
                <MatchPlayerRow
                    player={playerTwo}
                    highlighted={highlightUserId === playerTwo.userId}
                    isWinner={playerTwoIsWinner}
                />
            </div>

            {sets.length === 0 ? (
                <span className={cn(playerRowClassName, 'text-sm text-muted-foreground')}>—</span>
            ) : (
                <div className="flex gap-1.5 sm:gap-2">
                    {sets.map((set, index) => {
                        const playerOneWonSet = set.playerOneGames > set.playerTwoGames;
                        const playerTwoWonSet = set.playerTwoGames > set.playerOneGames;

                        return (
                            <div key={index} className="flex flex-col gap-1.5">
                                <span
                                    className={cn(
                                        scoreBoxClassName,
                                        playerOneWonSet
                                            ? 'font-bold'
                                            : 'font-medium text-muted-foreground',
                                    )}
                                >
                                    {set.playerOneGames}
                                </span>
                                <span
                                    className={cn(
                                        scoreBoxClassName,
                                        playerTwoWonSet
                                            ? 'font-bold'
                                            : 'font-medium text-muted-foreground',
                                    )}
                                >
                                    {set.playerTwoGames}
                                </span>
                            </div>
                        );
                    })}
                </div>
            )}
        </div>
    );
}

export function formatPlayedAtDate(value: string | null, locale: string): string | null {
    if (value === null) {
        return null;
    }

    const dateLocale = locale === 'hr' ? 'hr-HR' : 'en-GB';

    return new Date(value).toLocaleDateString(dateLocale, { dateStyle: 'medium' });
}
