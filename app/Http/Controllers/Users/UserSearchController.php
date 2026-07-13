<?php

declare(strict_types=1);

namespace App\Http\Controllers\Users;

use App\Actions\Users\SearchUsersAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Users\SearchUsersRequest;
use App\Http\Resources\UserSearchResultResource;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class UserSearchController extends Controller
{
    public function __invoke(SearchUsersRequest $request, SearchUsersAction $action): AnonymousResourceCollection
    {
        $users = $action->execute(
            query: (string) $request->validated('q'),
            limit: 10,
        );

        return UserSearchResultResource::collection($users);
    }
}
