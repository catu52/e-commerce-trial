<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Builder;

use App\Models\Role;
use App\Models\Permission;
use App\Models\FlashSale;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;

// Attributes for mass assignment and hidden fields
#[Fillable(['name', 'email', 'password'])]
// Attributes for hidden fields
#[Hidden(['password', 'remember_token'])]

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable, HasApiTokens;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    /* ========== Relationships ========== */

    /**
     * Many-to-many relationship between users and roles.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsToMany
     */
    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class, 'role_user');
    }

    /**
     * Many-to-many relationship between users and permissions.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsToMany
     */
    public function permissions(): BelongsToMany
    {
        return $this->belongsToMany(Permission::class, 'permission_user');
    }

    /**
     * One-to-many relationship between users and flash sales.
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function flashSales(): HasMany
    {
        return $this->hasMany(FlashSale::class, 'flash_sale_user');
    }

    /* ========== Query Scopes ========== */

    /**
     * Scope a query to only include users with a specific role.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param string $role
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeWithRole(Builder $query, string $role): Builder
    {
        return $query->whereHas('roles', function (Builder $query) use ($role) {
            $query->where('name', $role);
        });
    }

    /* ========== RBAC Methods ========== */

    /**
     * Check if the user has a specific role.
     *
     * @param string $role
     * @return bool
     */
    public function hasRole(string $role): bool
    {
        return $this->roles()->where('name', $role)->exists();
    }

    /**
     * Check if the user has a specific permission.
     *
     * @param string $permission
     * @return bool
     */
    public function hasPermission(string $permission): bool
    {
        // Check direct permissions first
         if($this->permissions()->where('name', $permission)->exists()) {
             return true;
         }

         // Check permissions through roles
         foreach($this->roles as $role) {
             if($role->permissions()->where('name', $permission)->exists()) {
                 return true;
             }
         }
         
         return false;
    }
}
