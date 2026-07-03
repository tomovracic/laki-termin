<?php

declare(strict_types=1);

namespace App\Http\Controllers\Leagues;

use App\Actions\Leagues\AddLeagueParticipantAction;
use App\Actions\Leagues\CreateLeagueAction;
use App\Actions\Leagues\RecordLeagueMatchResultAction;
use App\DTO\Leagues\AddLeagueParticipantData;
use App\DTO\Leagues\CreateLeagueData;
use App\DTO\Leagues\RecordLeagueMatchResultData;
use App\Http\Controllers\Controller;
use App\Http\Requests\Leagues\RecordLeagueMatchResultRequest;
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
        $validated = $request->validated();

        $league = $action->execute(new CreateLeagueData(
            name: (string) $validated['name'],
            rounds: (int) $validated['rounds'],
            createdBy: $request->user()->id,
            participantIds: array_map('intval', $validated['participant_ids']),
        ));

        return LeagueResource::make($league->loadCount(['participants', 'matches']));
    }

    public function storeParticipant(
        StoreLeagueParticipantRequest $request,
        League $league,
        AddLeagueParticipantAction $action,
    ): JsonResponse {
        $validated = $request->validated();

        $action->execute(new AddLeagueParticipantData(
            leagueId: $league->id,
            userId: (int) $validated['user_id'],
        ));

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
            set2PlayerOneGames: (int) $validated['set2_player_one_games'],
            set2PlayerTwoGames: (int) $validated['set2_player_two_games'],
            set3PlayerOneGames: isset($validated['set3_player_one_games']) ? (int) $validated['set3_player_one_games'] : null,
            set3PlayerTwoGames: isset($validated['set3_player_two_games']) ? (int) $validated['set3_player_two_games'] : null,
        ));

        return LeagueMatchResource::make($match);
    }
}
