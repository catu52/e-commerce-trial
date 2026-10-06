<?php

namespace App\Policies;

use App\Models\User;

class UserPolicy
{
    /**
     * Create a new policy instance.
     */
    public function __construct()
    {
        //
    }

    /**
     * Determine whether the authenticated user can view any users.
     *
     * @param  \App\Models\User  $authenticatedUser
     * @return bool
     */
    public function viewAny(User $authenticatedUser): bool
    {
        return $authenticatedUser->hasPermission('users.manage');
    }

    /**
     * Determine whether the authenticated user can view the specified user.
     *
     * @param  \App\Models\User  $authenticatedUser
     * @param  \App\Models\User  $targetUser
     * @return bool
     */
    public function view(User $authenticatedUser, User $targetUser): bool
    {
        return $authenticatedUser->hasPermission('users.manage') || 
            $authenticatedUser->id === $targetUser->id; 
    }

    /**
     * Determine whether the authenticated user can create a new user.
     *
     * @param  \App\Models\User  $authenticatedUser
     * @return bool
     */
    public function create(User $authenticatedUser): bool
    {
        return $authenticatedUser->hasPermission('users.manage');
    }

    /**
     * Determine whether the authenticated user can update the specified user.
     *
     * @param  \App\Models\User  $authenticatedUser
     * @param  \App\Models\User  $targetUser
     * @return bool
     */
    public function update(User $authenticatedUser, User $targetUser): bool
    {
        return $authenticatedUser->hasPermission('users.manage');
    }

    /**
     * Determine whether the authenticated user can delete the specified user.
     *
     * @param  \App\Models\User  $authenticatedUser
     * @param  \App\Models\User  $targetUser
     * @return bool
     */
    public function delete(User $authenticatedUser, User $targetUser): bool
    {
        // Prevent deleting oneself
        if ($authenticatedUser->id === $targetUser->id) {
            return false;
        }

        return $authenticatedUser->hasPermission('users.manage');
    }
}
