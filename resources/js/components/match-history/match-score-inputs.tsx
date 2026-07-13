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

export function MatchScoreInputs({ values, onChange, errors = [] }: MatchScoreInputsProps) {
    const { t } = useI18n();

    return (
        <div className="mx-auto flex w-full max-w-sm flex-col items-center gap-3">
            <SetRow
                label={`${t('league_set')} 1`}
                playerOneField="set1_player_one_games"
                playerTwoField="set1_player_two_games"
                values={values}
                onChange={onChange}
            />
            <SetRow
                label={`${t('league_set')} 2`}
                playerOneField="set2_player_one_games"
                playerTwoField="set2_player_two_games"
                values={values}
                onChange={onChange}
            />
            <SetRow
                label={`${t('league_set')} 3`}
                playerOneField="set3_player_one_games"
                playerTwoField="set3_player_two_games"
                values={values}
                onChange={onChange}
                required={false}
            />

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

export function buildScorePayload(values: MatchScoreValues) {
    const payload: {
        set1_player_one_games: number;
        set1_player_two_games: number;
        set2_player_one_games: number;
        set2_player_two_games: number;
        set3_player_one_games?: number | null;
        set3_player_two_games?: number | null;
    } = {
        set1_player_one_games: Number.parseInt(values.set1_player_one_games, 10),
        set1_player_two_games: Number.parseInt(values.set1_player_two_games, 10),
        set2_player_one_games: Number.parseInt(values.set2_player_one_games, 10),
        set2_player_two_games: Number.parseInt(values.set2_player_two_games, 10),
    };

    if (values.set3_player_one_games.trim() !== '' && values.set3_player_two_games.trim() !== '') {
        payload.set3_player_one_games = Number.parseInt(values.set3_player_one_games, 10);
        payload.set3_player_two_games = Number.parseInt(values.set3_player_two_games, 10);
    }

    return payload;
}
