<?php

declare(strict_types=1);

namespace App\Http\Controllers\MatchHistory;

use App\Actions\MatchHistory\CreatePlayedMatchAction;
use App\Actions\MatchHistory\DeletePlayedMatchAction;
use App\Actions\MatchHistory\UpdatePlayedMatchAction;
use App\DTO\MatchHistory\CreatePlayedMatchData;
use App\DTO\MatchHistory\MatchHistoryPlayerInputData;
use App\DTO\MatchHistory\UpdatePlayedMatchData;
use App\Http\Controllers\Controller;
use App\Http\Requests\MatchHistory\StorePlayedMatchRequest;
use App\Http\Requests\MatchHistory\UpdatePlayedMatchRequest;
use App\Http\Resources\PlayedMatchResource;
use App\Models\PlayedMatch;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Date;

class PlayedMatchController extends Controller
{
    public function store(StorePlayedMatchRequest $request, CreatePlayedMatchAction $action): PlayedMatchResource
    {
        $validated = $request->validated();
        $playerTwo = $validated['player_two'];

        $playedMatch = $action->execute(new CreatePlayedMatchData(
            currentUserId: $request->user()->id,
            playerTwo: new MatchHistoryPlayerInputData(
                userId: isset($playerTwo['user_id']) ? (int) $playerTwo['user_id'] : null,
                firstName: $playerTwo['first_name'] ?? null,
                lastName: $playerTwo['last_name'] ?? null,
            ),
            set1PlayerOneGames: (int) $validated['set1_player_one_games'],
            set1PlayerTwoGames: (int) $validated['set1_player_two_games'],
            set2PlayerOneGames: (int) $validated['set2_player_one_games'],
            set2PlayerTwoGames: (int) $validated['set2_player_two_games'],
            set3PlayerOneGames: isset($validated['set3_player_one_games']) ? (int) $validated['set3_player_one_games'] : null,
            set3PlayerTwoGames: isset($validated['set3_player_two_games']) ? (int) $validated['set3_player_two_games'] : null,
            playedAt: isset($validated['played_at'])
                ? Date::parse($validated['played_at'])
                : Date::now(),
        ));

        return PlayedMatchResource::make($playedMatch);
    }

    public function update(
        UpdatePlayedMatchRequest $request,
        PlayedMatch $playedMatch,
        UpdatePlayedMatchAction $action,
    ): PlayedMatchResource {
        $validated = $request->validated();

        $playedMatch = $action->execute($playedMatch, new UpdatePlayedMatchData(
            set1PlayerOneGames: (int) $validated['set1_player_one_games'],
            set1PlayerTwoGames: (int) $validated['set1_player_two_games'],
            set2PlayerOneGames: (int) $validated['set2_player_one_games'],
            set2PlayerTwoGames: (int) $validated['set2_player_two_games'],
            set3PlayerOneGames: isset($validated['set3_player_one_games']) ? (int) $validated['set3_player_one_games'] : null,
            set3PlayerTwoGames: isset($validated['set3_player_two_games']) ? (int) $validated['set3_player_two_games'] : null,
        ));

        return PlayedMatchResource::make($playedMatch);
    }

    public function destroy(PlayedMatch $playedMatch, DeletePlayedMatchAction $action): JsonResponse
    {
        $this->authorize('delete', $playedMatch);

        $action->execute($playedMatch);

        return response()->json([
            'data' => [
                'deleted' => true,
            ],
        ]);
    }
}
