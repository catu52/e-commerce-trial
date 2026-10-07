<?php

namespace App\Http\Controllers;

use App\Http\Requests\StaffLoginRequest;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class StaffAuthController extends Controller
{
    /**
     * Login an existing staff user and return a JSON response with
     * the staff user data and authentication token.
     */
    public function login(StaffLoginRequest $request): JsonResponse
    {
        // Validate the incoming request using the StaffLoginRequest
        $validated = $request->validated();

        // Attempt to find the staff user by email
        $staff = User::where('email', $validated['email'])->first();

        // Check if the staff user exists and the provided password is correct
        if (! $staff || ! Hash::check($validated['password'], $staff->password)) {
            throw ValidationException::withMessages([
                'email' => ['The provided credentials are incorrect.'],
            ]);
        }

        // Generate an authentication token for the authenticated staff user
        $token = $staff->createToken('staff_auth_token', ['staff'])->plainTextToken;

        // Return a JSON response with the staff user data and authentication token
        return response()->json([
            'message' => 'Logged in successfully',
            'staff' => $staff,
            'token' => $token,
        ]);
    }

    /**
     * Get the profile of the currently authenticated staff user.
     */
    public function profile(Request $request): JsonResponse
    {
        // Load the roles and permissions for the currently authenticated staff user
        $user = $request->user()->load('roles.permissions');

        // Extract the unique permissions from the user's roles
        $permissions = $user->roles->flatMap(function ($role) {
            return $role->permissions;
        })->pluck('name')->unique();

        // Return the staff user data along with their unique permissions
        return response()->json([
            'staff' => $user,
            'permissions' => $permissions,
        ]);
    }

    /**
     * Logout the currently authenticated staff user and revoke their authentication token.
     */
    public function logout(Request $request): JsonResponse
    {
        // Revoke the authentication token for the currently authenticated staff user
        $request->user()->currentAccessToken()->delete();

        // Return a JSON response indicating successful logout
        return response()->json([
            'message' => 'Logged out successfully',
        ]);
    }
}
