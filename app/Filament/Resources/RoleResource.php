<?php

namespace App\Filament\Resources;

use App\Filament\Concerns\AuthorizesByPermission;
use App\Filament\Resources\RoleResource\Pages;
use App\Models\Permission;
use App\Models\Role;
use BackedEnum;
use Filament\Actions;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

/** Roles and their permissions. Requires `users.manage`. */
class RoleResource extends Resource
{
    use AuthorizesByPermission;

    protected static ?string $model = Role::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-shield-check';

    protected static string|UnitEnum|null $navigationGroup = 'Security';

    protected static ?int $navigationSort = 2;

    protected static ?string $viewPermission = 'users.manage';

    protected static ?string $managePermission = 'users.manage';

    public static function canDelete(Model $record): bool
    {
        return static::staffCan('users.manage') && ! static::isLocked($record) && $record->users()->doesntExist();
    }

    public static function canDeleteAny(): bool
    {
        return static::staffCan('users.manage');
    }

    /** Built-in roles are referenced by name in code and cannot be renamed or removed. */
    public static function isLocked(Role $record): bool
    {
        return in_array($record->name, Role::BUILT_IN, true);
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Forms\Components\TextInput::make('name')
                ->required()
                ->maxLength(255)
                ->alphaDash()
                ->unique(ignoreRecord: true)
                ->disabled(fn (?Role $record) => $record && static::isLocked($record))
                ->dehydrated(fn (?Role $record) => ! ($record && static::isLocked($record)))
                ->helperText('Machine name, e.g. "editor".'),
            Forms\Components\TextInput::make('display_name')->required()->maxLength(255),
            Forms\Components\Textarea::make('description')->rows(2)->columnSpanFull(),
            Forms\Components\CheckboxList::make('permissions')
                ->relationship('permissions', 'display_name')
                ->descriptions(fn () => Permission::pluck('description', 'id')->all())
                ->columns(2)
                ->bulkToggleable()
                ->columnSpanFull(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('display_name')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('name')->badge()->searchable(),
                Tables\Columns\TextColumn::make('users_count')->counts('users')->label('Users')->sortable(),
                Tables\Columns\TextColumn::make('permissions_count')->counts('permissions')->label('Permissions')->sortable(),
            ])
            ->recordActions([
                Actions\EditAction::make(),
                Actions\DeleteAction::make()
                    ->hidden(fn (Role $record) => ! static::canDelete($record))
                    ->modalDescription('Roles that are built in or still assigned to users cannot be deleted.'),
            ])
            ->defaultSort('name');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListRoles::route('/'),
            'create' => Pages\CreateRole::route('/create'),
            'edit' => Pages\EditRole::route('/{record}/edit'),
        ];
    }
}
