<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Academic\Actions\SetTeacherSubject;
use App\Domain\Academic\Models\Subject;
use App\Domain\Identity\Actions\CreateUser;
use App\Domain\Identity\Actions\SyncUserRoles;
use App\Domain\Identity\Models\User;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\StoreUserRequest;
use App\Http\Requests\Api\V1\UpdateUserRolesRequest;
use App\Http\Requests\Api\V1\UpdateUserSubjectRequest;
use App\Http\Resources\Api\V1\UserResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;

/**
 * Client users. There is no registration: users with `user.manage`
 * create accounts and assign roles.
 */
class UserController extends Controller
{
    public function index(): AnonymousResourceCollection
    {
        Gate::authorize('viewAny', User::class);

        return UserResource::collection(
            User::query()->with(['roles', 'subject'])->orderBy('name')->paginate(50),
        );
    }

    public function store(StoreUserRequest $request, CreateUser $createUser): JsonResponse
    {
        $user = $createUser->handle(
            name: $request->validated('name'),
            email: $request->validated('email'),
            password: $request->validated('password'),
            roles: $request->validated('roles'),
            subjectId: $request->validated('subject_id'),
        );

        return (new UserResource($user))->response()->setStatusCode(201);
    }

    public function updateRoles(UpdateUserRolesRequest $request, User $user, SyncUserRoles $syncUserRoles): UserResource
    {
        return new UserResource($syncUserRoles->handle($user, $request->validated('roles'))->load('subject'));
    }

    public function updateSubject(UpdateUserSubjectRequest $request, User $user, SetTeacherSubject $setTeacherSubject): UserResource
    {
        $subjectId = $request->validated('subject_id');

        return new UserResource($setTeacherSubject->handle($user, $subjectId === null ? null : Subject::query()->findOrFail($subjectId)));
    }
}
