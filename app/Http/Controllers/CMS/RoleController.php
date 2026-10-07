<?php

namespace App\Http\Controllers\CMS;

use App\Http\Controllers\Controller;
use App\Models\Permission;
use App\Models\Role;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RoleController extends Controller
{
    /**
     * Display a listing of roles with their permissions.
     */
    public function index(): JsonResponse
    {
        $roles = Role::with('permissions')->get();

        return response()->json([
            'roles' => $roles,
        ]);
    }

    /**
     * Display a listing of all permissions.
     */
    public function permissions(): JsonResponse
    {
        $permissions = Permission::all();

        return response()->json([
            'permissions' => $permissions,
        ]);
    }

    /**
     * Sync permissions for the specified role.
     */
    public function syncPermissions(Request $request, Role $role): JsonResponse
    {
        // Validate the incoming request data.
        $request->validate([
            'permissions' => ['required', 'array'],
            'permissions.*' => ['integer', 'exists:permissions,id'],
        ]);

        // Sync the provided permissions with the role.
        $role->permissions()->sync($request->input('permissions'));

        return response()->json([
            'message' => "Permissions synced for role '{$role->name}'.",
            'role' => $role->load('permissions'),
        ]);
    }
}
