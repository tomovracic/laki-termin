<?php

declare(strict_types=1);

namespace App\Http\Controllers\Users;

use App\Actions\Users\CreateUserAction;
use App\Actions\Users\UpdateUserGroupsAction;
use App\Actions\Users\UpdateUserTokenCountAction;
use App\DTO\Users\CreateUserData;
use App\DTO\Users\UpdateUserGroupsData;
use App\DTO\Users\UpdateUserTokenCountData;
use App\Http\Controllers\Controller;
use App\Http\Requests\Users\StoreUserRequest;
use App\Http\Requests\Users\UpdateUserGroupsRequest;
use App\Http\Requests\Users\UpdateUserTokenCountRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Gate;

class UserController extends Controller
{
    public function index(): AnonymousResourceCollection
    {
        Gate::authorize('viewAny', User::class);

        $users = User::query()
            ->with('groups')
            ->orderBy('first_name')
            ->orderBy('last_name')
            ->paginate(20);

        return UserResource::collection($users);
    }

    public function updateTokenCount(
        UpdateUserTokenCountRequest $request,
        User $user,
        UpdateUserTokenCountAction $action,
    ): UserResource {
        $validated = $request->validated();

        $updatedUser = $action->execute($user, new UpdateUserTokenCountData(
            tokenCount: (int) $validated['token_count'],
        ));

        return UserResource::make($updatedUser->load('groups'));
    }

    public function updateGroups(
        UpdateUserGroupsRequest $request,
        User $user,
        UpdateUserGroupsAction $action,
    ): UserResource {
        $validated = $request->validated();

        /** @var list<int> $groupIds */
        $groupIds = array_map('intval', $validated['group_ids']);

        $updatedUser = $action->execute($user, new UpdateUserGroupsData(
            groupIds: $groupIds,
        ));

        return UserResource::make($updatedUser);
    }

    public function store(StoreUserRequest $request, CreateUserAction $action): JsonResource
    {
        $validated = $request->validated();

        /** @var list<int> $groupIds */
        $groupIds = array_map('intval', $validated['group_ids']);

        $createdUser = $action->execute(new CreateUserData(
            email: (string) $validated['email'],
            groupIds: $groupIds,
        ));

        return UserResource::make($createdUser);
    }
}
