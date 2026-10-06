<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Factories\HasFactory;

use App\Models\Role;
use App\Models\User;

use Illuminate\Database\Eloquent\Attributes\Fillable;

// Attributes for mass assignment
#[Fillable(['name', 'display_name', 'description'])]

class Permission extends Model
{
    use HasFactory;

    /**
     * Many-to-many relationship between permissions and roles.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsToMany
     */
    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class, 'permission_role');
    }

    /**
     * Many-to-many relationship between permissions and users.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsToMany
     */
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'permission_user');
    }
}
