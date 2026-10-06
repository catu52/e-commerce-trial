<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Factories\HasFactory;

use App\Models\Permission;
use App\Models\User;

use Illuminate\Database\Eloquent\Attributes\Fillable;

// Attributes for mass assignment
#[Fillable(['name', 'display_name', 'description'])]

class Role extends Model
{
    use HasFactory;

    /**
     * Many-to-many relationship between roles and users.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsToMany
     */
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'role_user');
    }

    /**
     * Many-to-many relationship between roles and permissions.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsToMany
     */
    public function permissions(): BelongsToMany
    {
        return $this->belongsToMany(Permission::class, 'permission_role');
    }
}
