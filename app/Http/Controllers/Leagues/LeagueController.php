<?php

declare(strict_types=1);

namespace App\Http\Controllers\Leagues;

use App\Actions\Leagues\AddLeagueParticipantAction;
use App\Actions\Leagues\CreateLeagueAction;
use App\Actions\Leagues\DeleteLeagueAction;
use App\Actions\Leagues\FinishKnockoutRoundAction;
use App\Actions\Leagues\RecordLeagueMatchResultAction;
use App\Actions\Leagues\StartKnockoutFromGroupsAction;
use App\DTO\Leagues\RecordLeagueMatchResultData;
use App\Http\Controllers\Controller;
use App\Http\Requests\Leagues\FinishKnockoutRoundRequest;
use App\Http\Requests\Leagues\RecordLeagueMatchResultRequest;
use App\Http\Requests\Leagues\StartKnockoutFromGroupsRequest;
use App\Http\Requests\Leagues\StoreLeagueParticipantRequest;
use App\Http\Requests\Leagues\StoreLeagueRequest;
use App\Http\Resources\LeagueMatchResource;
use App\Http\Resources\LeagueResource;
use App\Models\League;
use App\Models\LeagueMatch;
use Illuminate\Http\JsonResponse;

class LeagueController extends Controller
{
    public function store(StoreLeagueRequest $request, CreateLeagueAction $action): LeagueResource
    {
        $league = $action->execute($request->toCreateLeagueData());

        return LeagueResource::make($league->loadCount(['participants', 'matches']));
    }

    public function storeParticipant(
        StoreLeagueParticipantRequest $request,
        League $league,
        AddLeagueParticipantAction $action,
    ): JsonResponse {
        $action->execute($request->toAddLeagueParticipantData($league));

        return response()->json([
            'message' => 'Sudionik je dodan u ligu.',
        ]);
    }

    public function updateMatchResult(
        RecordLeagueMatchResultRequest $request,
        League $league,
        LeagueMatch $match,
        RecordLeagueMatchResultAction $action,
    ): LeagueMatchResource {
        $validated = $request->validated();

        $match = $action->execute(new RecordLeagueMatchResultData(
            matchId: $match->id,
            enteredBy: $request->user()->id,
            set1PlayerOneGames: (int) $validated['set1_player_one_games'],
            set1PlayerTwoGames: (int) $validated['set1_player_two_games'],
            set2PlayerOneGames: isset($validated['set2_player_one_games']) ? (int) $validated['set2_player_one_games'] : null,
            set2PlayerTwoGames: isset($validated['set2_player_two_games']) ? (int) $validated['set2_player_two_games'] : null,
            set3PlayerOneGames: isset($validated['set3_player_one_games']) ? (int) $validated['set3_player_one_games'] : null,
            set3PlayerTwoGames: isset($validated['set3_player_two_games']) ? (int) $validated['set3_player_two_games'] : null,
            set4PlayerOneGames: isset($validated['set4_player_one_games']) ? (int) $validated['set4_player_one_games'] : null,
            set4PlayerTwoGames: isset($validated['set4_player_two_games']) ? (int) $validated['set4_player_two_games'] : null,
            set5PlayerOneGames: isset($validated['set5_player_one_games']) ? (int) $validated['set5_player_one_games'] : null,
            set5PlayerTwoGames: isset($validated['set5_player_two_games']) ? (int) $validated['set5_player_two_games'] : null,
        ));

        return LeagueMatchResource::make($match);
    }

    public function finishRound(
        FinishKnockoutRoundRequest $request,
        League $league,
        FinishKnockoutRoundAction $action,
    ): LeagueResource {
        $league = $action->execute($league);

        return LeagueResource::make($league->loadCount(['participants', 'matches']));
    }

    public function startKnockout(
        StartKnockoutFromGroupsRequest $request,
        League $league,
        StartKnockoutFromGroupsAction $action,
    ): LeagueResource {
        $league = $action->execute($league);

        return LeagueResource::make($league->loadCount(['participants', 'matches']));
    }

    public function destroy(League $league, DeleteLeagueAction $action): JsonResponse
    {
        $this->authorize('delete', $league);

        $action->execute($league);

        return response()->json([
            'data' => [
                'deleted' => true,
            ],
        ]);
    }
}
