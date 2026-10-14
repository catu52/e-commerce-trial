<?php

namespace App\Filament\Concerns;

use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Database\Eloquent\Model;

/**
 * Authorises a Filament resource through the app's own RBAC (User::hasPermission()).
 *
 * Declare `$viewPermission` (list/view) and `$managePermission` (create/edit/delete) on the resource.
 */
trait AuthorizesByPermission
{
    public static function staffCan(?string $permission): bool
    {
        $user = Filament::auth()->user();

        return $user instanceof User && $permission !== null && $user->hasPermission($permission);
    }

    public static function canViewAny(): bool
    {
        return static::staffCan(static::$viewPermission);
    }

    public static function canView(Model $record): bool
    {
        return static::staffCan(static::$viewPermission);
    }

    public static function canCreate(): bool
    {
        return static::staffCan(static::$managePermission);
    }

    public static function canEdit(Model $record): bool
    {
        return static::staffCan(static::$managePermission);
    }

    public static function canDelete(Model $record): bool
    {
        return static::staffCan(static::$managePermission);
    }

    public static function canDeleteAny(): bool
    {
        return static::staffCan(static::$managePermission);
    }
}
