<?php

namespace App\Filament\Resources;

use App\Filament\Concerns\AuthorizesByPermission;
use App\Filament\Resources\UserResource\Pages;
use App\Models\Role;
use App\Models\User;
use BackedEnum;
use Filament\Actions;
use Filament\Facades\Filament;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

/** Staff users (CMS accounts). Requires `users.manage`. */
class UserResource extends Resource
{
    use AuthorizesByPermission;

    protected static ?string $model = User::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-users';

    protected static string|UnitEnum|null $navigationGroup = 'Security';

    protected static ?string $navigationLabel = 'Staff users';

    protected static ?string $modelLabel = 'staff user';

    protected static ?int $navigationSort = 1;

    protected static ?string $viewPermission = 'users.manage';

    protected static ?string $managePermission = 'users.manage';

    public static function canDelete(Model $record): bool
    {
        return static::staffCan('users.manage') && ! static::isProtected($record);
    }

    /** Nobody may delete their own account or the last remaining admin. */
    public static function isProtected(User $record): bool
    {
        return $record->is(Filament::auth()->user())
            || ($record->hasRole('admin') && User::withRole('admin')->count() <= 1);
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Forms\Components\TextInput::make('name')->required()->maxLength(255),
            Forms\Components\TextInput::make('email')->email()->required()->maxLength(255)->unique(ignoreRecord: true),
            Forms\Components\TextInput::make('password')
                ->password()
                ->revealable()
                ->minLength(8)
                ->required(fn (string $operation) => $operation === 'create')
                ->dehydrated(fn (?string $state) => filled($state))
                ->helperText('Leave empty to keep the current password.'),
            Forms\Components\Select::make('roles')
                ->relationship('roles', 'display_name')
                ->multiple()
                ->preload()
                ->helperText('Permissions are inherited from the selected roles.')
                ->rules([
                    fn (?Model $record) => function (string $attribute, $value, \Closure $fail) use ($record) {
                        if (! $record instanceof User || ! $record->hasRole('admin') || User::withRole('admin')->count() > 1) {
                            return;
                        }
                        $adminId = Role::where('name', 'admin')->value('id');
                        if (! in_array($adminId, array_map('intval', (array) $value), true)) {
                            $fail('The last remaining administrator must keep the admin role.');
                        }
                    },
                ]),
            Forms\Components\Select::make('permissions')
                ->label('Extra permissions')
                ->relationship('permissions', 'display_name')
                ->multiple()
                ->preload()
                ->helperText('Direct grants on top of the role permissions.'),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->with('roles'))
            ->columns([
                Tables\Columns\TextColumn::make('name')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('email')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('roles.display_name')->label('Roles')->badge(),
                Tables\Columns\TextColumn::make('tokens_count')->counts('tokens')->label('Tokens')->sortable(),
                Tables\Columns\TextColumn::make('created_at')->dateTime()->sortable()->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('role')
                    ->relationship('roles', 'display_name')
                    ->preload(),
            ])
            ->recordActions([
                Actions\EditAction::make(),
                Actions\DeleteAction::make()->hidden(fn (User $record) => static::isProtected($record)),
            ])
            ->defaultSort('name');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListUsers::route('/'),
            'create' => Pages\CreateUser::route('/create'),
            'edit' => Pages\EditUser::route('/{record}/edit'),
        ];
    }
}
