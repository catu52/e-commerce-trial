<?php

namespace App\Http\Controllers\CMS;

use App\Http\Controllers\Controller;
use App\Http\Requests\CMS\StoreUserRequest;
use App\Http\Requests\CMS\UpdateUserRequest;
use App\Models\User;
use App\Services\UserService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class UserController extends Controller
{
    /**
     * UserController constructor.
     */
    public function __construct(protected UserService $userService)
    {
        $this->authorizeResource(User::class, 'user');
    }

    /**
     * Display a paginated list of users.
     */
    public function index(Request $request): JsonResponse
    {
        // Retrieve paginated list of users based on query parameters.
        $users = $this->userService->getPaginatedUsers(
            perPage: (int) $request->query('per_page', 15),
            search: $request->query('search')
        );

        return response()->json($users);
    }

    /**
     * Store a newly created user in the system.
     */
    public function store(StoreUserRequest $request): JsonResponse
    {
        $user = $this->userService->createUser($request->validated());

        return response()->json([
            'message' => 'Staff user created successfully.',
            'user' => $user,
        ], 201);
    }

    /**
     * Display the specified user.
     */
    public function show(User $user): JsonResponse
    {
        return response()->json([
            'user' => $user->load(['roles.permissions', 'permissions']),
        ]);
    }

    /**
     * Update the specified user in the system.
     */
    public function update(UpdateUserRequest $request, User $user): JsonResponse
    {
        $updatedUser = $this->userService->updateUser($user, $request->validated());

        return response()->json([
            'message' => 'Staff user updated successfully.',
            'user' => $updatedUser,
        ]);
    }

    /**
     * Remove the specified user from the system.
     */
    public function destroy(User $user): JsonResponse
    {
        $this->userService->deleteUser($user);

        return response()->json([
            'message' => 'Staff user deleted successfully.',
        ]);
    }
}
