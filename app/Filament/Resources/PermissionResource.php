<?php

namespace App\Filament\Resources;

use App\Filament\Concerns\AuthorizesByPermission;
use App\Filament\Resources\PermissionResource\Pages;
use App\Models\Permission;
use BackedEnum;
use Filament\Actions;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

/**
 * Permission catalogue. Names are checked in code, so they are read-only here;
 * only the label and description are editable. Requires `users.manage`.
 */
class PermissionResource extends Resource
{
    use AuthorizesByPermission;

    protected static ?string $model = Permission::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-lock-closed';

    protected static string|UnitEnum|null $navigationGroup = 'Security';

    protected static ?int $navigationSort = 3;

    protected static ?string $viewPermission = 'users.manage';

    protected static ?string $managePermission = 'users.manage';

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canDelete(Model $record): bool
    {
        return false;
    }

    public static function canDeleteAny(): bool
    {
        return false;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Forms\Components\TextInput::make('name')->disabled()->dehydrated(false),
            Forms\Components\TextInput::make('display_name')->required()->maxLength(255),
            Forms\Components\Textarea::make('description')->rows(2)->columnSpanFull(),
            Forms\Components\Select::make('roles')
                ->relationship('roles', 'display_name')
                ->multiple()
                ->preload()
                ->columnSpanFull(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')->badge()->searchable()->sortable(),
                Tables\Columns\TextColumn::make('display_name')->searchable(),
                Tables\Columns\TextColumn::make('description')->wrap()->limit(80),
                Tables\Columns\TextColumn::make('roles.display_name')->label('Roles')->badge(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('role')->relationship('roles', 'display_name')->preload(),
            ])
            ->recordActions([Actions\EditAction::make()])
            ->defaultSort('name');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListPermissions::route('/'),
            'edit' => Pages\EditPermission::route('/{record}/edit'),
        ];
    }
}
