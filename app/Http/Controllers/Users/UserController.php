<?php

declare(strict_types=1);

namespace App\Http\Controllers\Users;

use App\Actions\Users\UpdateUserTokenCountAction;
use App\DTO\Users\UpdateUserTokenCountData;
use App\Http\Controllers\Controller;
use App\Http\Requests\Users\UpdateUserTokenCountRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;

class UserController extends Controller
{
    public function index(): AnonymousResourceCollection
    {
        Gate::authorize('viewAny', User::class);

        $users = User::query()
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

        return UserResource::make($updatedUser);
    }
}
