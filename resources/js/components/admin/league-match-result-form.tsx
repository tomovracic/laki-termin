import type { FormEvent } from 'react';
import { useEffect, useState } from 'react';
import InputError from '@/components/input-error';
import type { LeagueMatch, LeagueMatchResultPayload } from '@/components/league/types';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useI18n } from '@/lib/i18n';

type LeagueMatchResultFormProps = {
    match: LeagueMatch;
    onSubmit: (payload: LeagueMatchResultPayload) => Promise<void>;
    onCancel: () => void;
    isSubmitting: boolean;
    errors?: string[];
};

export function LeagueMatchResultForm({
    match,
    onSubmit,
    onCancel,
    isSubmitting,
    errors = [],
}: LeagueMatchResultFormProps) {
    const { t } = useI18n();

    const [set1PlayerOne, setSet1PlayerOne] = useState(
        match.set1_player_one_games !== null ? `${match.set1_player_one_games}` : '',
    );
    const [set1PlayerTwo, setSet1PlayerTwo] = useState(
        match.set1_player_two_games !== null ? `${match.set1_player_two_games}` : '',
    );
    const [set2PlayerOne, setSet2PlayerOne] = useState(
        match.set2_player_one_games !== null ? `${match.set2_player_one_games}` : '',
    );
    const [set2PlayerTwo, setSet2PlayerTwo] = useState(
        match.set2_player_two_games !== null ? `${match.set2_player_two_games}` : '',
    );
    const [set3PlayerOne, setSet3PlayerOne] = useState(
        match.set3_player_one_games !== null ? `${match.set3_player_one_games}` : '',
    );
    const [set3PlayerTwo, setSet3PlayerTwo] = useState(
        match.set3_player_two_games !== null ? `${match.set3_player_two_games}` : '',
    );

    useEffect(() => {
        setSet1PlayerOne(match.set1_player_one_games !== null ? `${match.set1_player_one_games}` : '');
        setSet1PlayerTwo(match.set1_player_two_games !== null ? `${match.set1_player_two_games}` : '');
        setSet2PlayerOne(match.set2_player_one_games !== null ? `${match.set2_player_one_games}` : '');
        setSet2PlayerTwo(match.set2_player_two_games !== null ? `${match.set2_player_two_games}` : '');
        setSet3PlayerOne(match.set3_player_one_games !== null ? `${match.set3_player_one_games}` : '');
        setSet3PlayerTwo(match.set3_player_two_games !== null ? `${match.set3_player_two_games}` : '');
    }, [match]);

    async function handleSubmit(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();

        const payload: LeagueMatchResultPayload = {
            set1_player_one_games: Number.parseInt(set1PlayerOne, 10),
            set1_player_two_games: Number.parseInt(set1PlayerTwo, 10),
            set2_player_one_games: Number.parseInt(set2PlayerOne, 10),
            set2_player_two_games: Number.parseInt(set2PlayerTwo, 10),
        };

        if (set3PlayerOne.trim() !== '' && set3PlayerTwo.trim() !== '') {
            payload.set3_player_one_games = Number.parseInt(set3PlayerOne, 10);
            payload.set3_player_two_games = Number.parseInt(set3PlayerTwo, 10);
        }

        await onSubmit(payload);
    }

    return (
        <form onSubmit={handleSubmit} className="space-y-4">
            <p className="text-sm text-muted-foreground">
                {match.player_one.name} {t('league_vs')} {match.player_two.name} ({t('league_round')} {match.round})
            </p>

            <div className="grid gap-4 sm:grid-cols-2">
                <div className="space-y-2">
                    <Label>{t('league_set')} 1 — {match.player_one.name}</Label>
                    <Input
                        type="number"
                        min={0}
                        value={set1PlayerOne}
                        onChange={(event) => setSet1PlayerOne(event.target.value)}
                        required
                    />
                </div>
                <div className="space-y-2">
                    <Label>{t('league_set')} 1 — {match.player_two.name}</Label>
                    <Input
                        type="number"
                        min={0}
                        value={set1PlayerTwo}
                        onChange={(event) => setSet1PlayerTwo(event.target.value)}
                        required
                    />
                </div>
                <div className="space-y-2">
                    <Label>{t('league_set')} 2 — {match.player_one.name}</Label>
                    <Input
                        type="number"
                        min={0}
                        value={set2PlayerOne}
                        onChange={(event) => setSet2PlayerOne(event.target.value)}
                        required
                    />
                </div>
                <div className="space-y-2">
                    <Label>{t('league_set')} 2 — {match.player_two.name}</Label>
                    <Input
                        type="number"
                        min={0}
                        value={set2PlayerTwo}
                        onChange={(event) => setSet2PlayerTwo(event.target.value)}
                        required
                    />
                </div>
                <div className="space-y-2">
                    <Label>{t('league_set')} 3 — {match.player_one.name} ({t('league_optional')})</Label>
                    <Input
                        type="number"
                        min={0}
                        value={set3PlayerOne}
                        onChange={(event) => setSet3PlayerOne(event.target.value)}
                    />
                </div>
                <div className="space-y-2">
                    <Label>{t('league_set')} 3 — {match.player_two.name} ({t('league_optional')})</Label>
                    <Input
                        type="number"
                        min={0}
                        value={set3PlayerTwo}
                        onChange={(event) => setSet3PlayerTwo(event.target.value)}
                    />
                </div>
            </div>

            {errors.map((error) => (
                <InputError key={error} message={error} />
            ))}

            <div className="flex justify-end gap-2">
                <Button type="button" variant="outline" onClick={onCancel} disabled={isSubmitting}>
                    {t('cancel')}
                </Button>
                <Button type="submit" disabled={isSubmitting}>
                    {isSubmitting ? t('saving') : t('save')}
                </Button>
            </div>
        </form>
    );
}
