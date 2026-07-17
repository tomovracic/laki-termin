import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useI18n } from '@/lib/i18n';
import { Minus, Plus } from 'lucide-react';

export type MatchScoreValues = {
    set1_player_one_games: string;
    set1_player_two_games: string;
    set2_player_one_games: string;
    set2_player_two_games: string;
    set3_player_one_games: string;
    set3_player_two_games: string;
    set4_player_one_games: string;
    set4_player_two_games: string;
    set5_player_one_games: string;
    set5_player_two_games: string;
};

const scoreInputClassName =
    'h-14 w-14 rounded-lg px-0 text-center text-xl font-semibold tabular-nums [appearance:textfield] [&::-webkit-inner-spin-button]:appearance-none [&::-webkit-outer-spin-button]:appearance-none md:h-16 md:w-16 md:text-2xl';

const stepperButtonClassName = 'h-6 w-9 shrink-0 rounded-md md:h-7 md:w-10';

type VerticalSteppersProps = {
    onDecrease: () => void;
    onIncrease: () => void;
    decreaseLabel: string;
    increaseLabel: string;
};

function VerticalSteppers({ onDecrease, onIncrease, decreaseLabel, increaseLabel }: VerticalSteppersProps) {
    return (
        <div className="flex h-14 flex-col justify-between md:h-16">
            <Button
                type="button"
                variant="outline"
                size="icon"
                className={stepperButtonClassName}
                onClick={onIncrease}
                aria-label={increaseLabel}
            >
                <Plus className="size-3.5 md:size-4" />
            </Button>
            <Button
                type="button"
                variant="outline"
                size="icon"
                className={stepperButtonClassName}
                onClick={onDecrease}
                aria-label={decreaseLabel}
            >
                <Minus className="size-3.5 md:size-4" />
            </Button>
        </div>
    );
}

type MatchScoreInputsProps = {
    values: MatchScoreValues;
    onChange: (values: MatchScoreValues) => void;
    errors?: string[];
    bestOf?: 1 | 3 | 5;
};

type SetRowProps = {
    label: string;
    playerOneField: keyof MatchScoreValues;
    playerTwoField: keyof MatchScoreValues;
    values: MatchScoreValues;
    onChange: (values: MatchScoreValues) => void;
    required?: boolean;
};

function parseScoreValue(value: string): number {
    if (value.trim() === '') {
        return 0;
    }

    const parsed = Number.parseInt(value, 10);

    return Number.isNaN(parsed) ? 0 : parsed;
}

function SetRow({
    label,
    playerOneField,
    playerTwoField,
    values,
    onChange,
    required = true,
}: SetRowProps) {
    function updateField(field: keyof MatchScoreValues, value: string) {
        onChange({
            ...values,
            [field]: value,
        });
    }

    function adjust(field: keyof MatchScoreValues, delta: number) {
        updateField(field, String(Math.max(0, parseScoreValue(values[field]) + delta)));
    }

    return (
        <div className="flex items-center justify-center gap-2 md:gap-3">
            <div className="flex items-center gap-1">
                <VerticalSteppers
                    onDecrease={() => adjust(playerOneField, -1)}
                    onIncrease={() => adjust(playerOneField, 1)}
                    decreaseLabel="Decrease left score"
                    increaseLabel="Increase left score"
                />
                <Input
                    type="number"
                    min={0}
                    inputMode="numeric"
                    pattern="[0-9]*"
                    value={values[playerOneField]}
                    onChange={(event) => updateField(playerOneField, event.target.value)}
                    required={required}
                    className={scoreInputClassName}
                />
            </div>

            <Label className="w-12 shrink-0 text-center text-sm font-medium text-muted-foreground md:w-14">
                {label}
            </Label>

            <div className="flex items-center gap-1">
                <Input
                    type="number"
                    min={0}
                    inputMode="numeric"
                    pattern="[0-9]*"
                    value={values[playerTwoField]}
                    onChange={(event) => updateField(playerTwoField, event.target.value)}
                    required={required}
                    className={scoreInputClassName}
                />
                <VerticalSteppers
                    onDecrease={() => adjust(playerTwoField, -1)}
                    onIncrease={() => adjust(playerTwoField, 1)}
                    decreaseLabel="Decrease right score"
                    increaseLabel="Increase right score"
                />
            </div>
        </div>
    );
}

const SET_ROWS: Array<{
    labelKey: number;
    playerOneField: keyof MatchScoreValues;
    playerTwoField: keyof MatchScoreValues;
}> = [
    {
        labelKey: 1,
        playerOneField: 'set1_player_one_games',
        playerTwoField: 'set1_player_two_games',
    },
    {
        labelKey: 2,
        playerOneField: 'set2_player_one_games',
        playerTwoField: 'set2_player_two_games',
    },
    {
        labelKey: 3,
        playerOneField: 'set3_player_one_games',
        playerTwoField: 'set3_player_two_games',
    },
    {
        labelKey: 4,
        playerOneField: 'set4_player_one_games',
        playerTwoField: 'set4_player_two_games',
    },
    {
        labelKey: 5,
        playerOneField: 'set5_player_one_games',
        playerTwoField: 'set5_player_two_games',
    },
];

export function MatchScoreInputs({
    values,
    onChange,
    errors = [],
    bestOf = 3,
}: MatchScoreInputsProps) {
    const { t } = useI18n();
    const maxSets = bestOf;
    const setsToWin = Math.ceil(bestOf / 2);

    return (
        <div className="mx-auto flex w-full max-w-sm flex-col items-center gap-3">
            {SET_ROWS.slice(0, maxSets).map((row, index) => (
                <SetRow
                    key={row.labelKey}
                    label={`${t('league_set')} ${row.labelKey}`}
                    playerOneField={row.playerOneField}
                    playerTwoField={row.playerTwoField}
                    values={values}
                    onChange={onChange}
                    required={index < setsToWin}
                />
            ))}

            {errors.length > 0 && (
                <div className="w-full space-y-1 text-center">
                    {errors.map((error) => (
                        <InputError key={error} message={error} />
                    ))}
                </div>
            )}
        </div>
    );
}

export function emptyMatchScoreValues(): MatchScoreValues {
    return {
        set1_player_one_games: '',
        set1_player_two_games: '',
        set2_player_one_games: '',
        set2_player_two_games: '',
        set3_player_one_games: '',
        set3_player_two_games: '',
        set4_player_one_games: '',
        set4_player_two_games: '',
        set5_player_one_games: '',
        set5_player_two_games: '',
    };
}

export function buildScorePayload(values: MatchScoreValues, bestOf: 1 | 3 | 5 = 3) {
    const payload: {
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
    } = {
        set1_player_one_games: Number.parseInt(values.set1_player_one_games, 10),
        set1_player_two_games: Number.parseInt(values.set1_player_two_games, 10),
    };

    if (bestOf >= 3) {
        payload.set2_player_one_games = Number.parseInt(values.set2_player_one_games || '0', 10);
        payload.set2_player_two_games = Number.parseInt(values.set2_player_two_games || '0', 10);
    }

    const optionalPairs: Array<{
        one: keyof MatchScoreValues;
        two: keyof MatchScoreValues;
        minBestOf: number;
        assign: (one: number, two: number) => void;
    }> = [
        {
            one: 'set3_player_one_games',
            two: 'set3_player_two_games',
            minBestOf: 3,
            assign: (one, two) => {
                payload.set3_player_one_games = one;
                payload.set3_player_two_games = two;
            },
        },
        {
            one: 'set4_player_one_games',
            two: 'set4_player_two_games',
            minBestOf: 5,
            assign: (one, two) => {
                payload.set4_player_one_games = one;
                payload.set4_player_two_games = two;
            },
        },
        {
            one: 'set5_player_one_games',
            two: 'set5_player_two_games',
            minBestOf: 5,
            assign: (one, two) => {
                payload.set5_player_one_games = one;
                payload.set5_player_two_games = two;
            },
        },
    ];

    for (const pair of optionalPairs) {
        if (bestOf < pair.minBestOf) {
            continue;
        }

        if (values[pair.one].trim() !== '' && values[pair.two].trim() !== '') {
            pair.assign(
                Number.parseInt(values[pair.one], 10),
                Number.parseInt(values[pair.two], 10),
            );
        }
    }

    return payload;
}
