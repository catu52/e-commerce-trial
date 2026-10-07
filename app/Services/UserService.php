<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class UserService
{
    /**
     * Get a paginated list of users, optionally filtered by a search term.
     */
    public function getPaginatedUsers(int $perPage = 15, ?string $search = null): LengthAwarePaginator
    {
        // Get a paginated list of users.
        // Lazily loads the roles and permissions relationships for each user.
        return User::with(['roles', 'permissions'])
            ->when($search, function ($query, $search) {
                $query->where('name', 'ilike', "%{$search}%")
                    ->orWhere('email', 'ilike', "%{$search}%");
            })
            ->latest() // Order by the most recently created users first
            ->paginate($perPage);
    }

    /**
     * Create a new user with the given data.
     */
    public function createUser(array $data): User
    {
        // Start a database transaction to ensure atomicity
        // of user creation and role/permission assignment.
        return DB::transaction(function () use ($data) {
            $user = User::create([
                'name' => $data['name'],
                'email' => $data['email'],
                'password' => Hash::make($data['password']), // Ensure the password is hashed before creating the user.
            ]);

            // Assign roles to the newly created user.
            if (! empty($data['roles'])) {
                $user->roles()->sync($data['roles']);
            }

            // Assign permissions to the newly created user.
            if (isset($data['permissions'])) {
                $user->permissions()->sync($data['permissions']);
            }

            return $user->load(['roles', 'permissions']);
        });
    }

    /**
     * Update the specified user with the given data.
     */
    public function updateUser(User $user, array $data): User
    {
        // Start a database transaction to ensure atomicity
        // of user update and role/permission assignment.
        return DB::transaction(function () use ($user, $data) {
            $updateData = [];

            if (isset($data['name'])) {
                $updateData['name'] = $data['name'];
            }

            if (isset($data['email'])) {
                $updateData['email'] = $data['email'];
            }

            if (! empty($data['password'])) {
                $updateData['password'] = Hash::make($data['password']);
            }

            if (! empty($updateData)) {
                $user->update($updateData);
            }

            if (isset($data['roles'])) {
                $user->roles()->sync($data['roles']);
            }

            if (array_key_exists('permissions', $data)) {
                $user->permissions()->sync($data['permissions'] ?? []);
            }

            return $user->load(['roles', 'permissions']);
        });
    }

    /**
     * Delete the specified user.
     */
    public function deleteUser(User $user): bool
    {
        // Start a database transaction to ensure atomicity
        // of user deletion and role/permission detachment.
        return DB::transaction(function () use ($user) {
            $user->roles()->detach(); // Detach all roles from the user before deletion.
            $user->permissions()->detach(); // Detach all permissions from the user before deletion.

            return $user->delete(); // Delete the user after detaching roles and permissions.
        });
    }
}
