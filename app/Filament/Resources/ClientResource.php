<?php

namespace App\Filament\Resources;

use App\Filament\Concerns\AuthorizesByPermission;
use App\Filament\Resources\ClientResource\Pages;
use App\Filament\Resources\ClientResource\RelationManagers\OrdersRelationManager;
use App\Models\Client;
use BackedEnum;
use Filament\Actions;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

/**
 * Storefront customers. Viewing needs `clients.view`; edits and account actions need `clients.manage`.
 * Customers with orders can't be deleted because orders cascade from the client.
 */
class ClientResource extends Resource
{
    use AuthorizesByPermission;

    protected static ?string $model = Client::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-user-group';

    protected static string|UnitEnum|null $navigationGroup = 'Sales';

    protected static ?string $navigationLabel = 'Customers';

    protected static ?string $modelLabel = 'customer';

    protected static ?string $recordTitleAttribute = 'email';

    protected static ?string $viewPermission = 'clients.view';

    protected static ?string $managePermission = 'clients.manage';

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canDelete(Model $record): bool
    {
        return static::staffCan('clients.manage') && $record->orders()->doesntExist();
    }

    public static function canDeleteAny(): bool
    {
        return false;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Forms\Components\TextInput::make('first_name')->required()->maxLength(255),
            Forms\Components\TextInput::make('last_name')->required()->maxLength(255),
            Forms\Components\TextInput::make('email')->email()->required()->maxLength(255)->unique(ignoreRecord: true),
            Forms\Components\TextInput::make('phone')->tel()->maxLength(50),
            Forms\Components\DateTimePicker::make('email_verified_at')->label('Email verified at'),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('first_name')->label('Name')
                    ->formatStateUsing(fn ($state, Client $record) => $record->name)
                    ->searchable(['first_name', 'last_name'])->sortable(),
                Tables\Columns\TextColumn::make('email')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('phone')->placeholder('—')->toggleable(),
                Tables\Columns\IconColumn::make('email_verified_at')->label('Verified')->boolean()
                    ->getStateUsing(fn (Client $record) => $record->email_verified_at !== null),
                Tables\Columns\TextColumn::make('orders_count')->counts('orders')->label('Orders')->sortable(),
                Tables\Columns\TextColumn::make('tokens_count')->counts('tokens')->label('Sessions')->sortable(),
                Tables\Columns\TextColumn::make('created_at')->label('Registered')->dateTime()->sortable(),
            ])
            ->filters([
                Tables\Filters\TernaryFilter::make('verified')->label('Email verified')
                    ->queries(
                        true: fn ($query) => $query->whereNotNull('email_verified_at'),
                        false: fn ($query) => $query->whereNull('email_verified_at'),
                    ),
                Tables\Filters\Filter::make('has_orders')->label('Has orders')
                    ->query(fn ($query) => $query->whereHas('orders')),
            ])
            ->recordActions([
                Actions\EditAction::make(),
                self::resetPasswordAction(),
                self::revokeSessionsAction(),
                Actions\DeleteAction::make()->hidden(fn (Client $record) => ! static::canDelete($record)),
            ])
            ->defaultSort('created_at', 'desc');
    }

    public static function resetPasswordAction(): Actions\Action
    {
        return Actions\Action::make('resetPassword')
            ->label('Set password')
            ->icon('heroicon-o-key')
            ->visible(fn () => static::staffCan('clients.manage'))
            ->schema([
                Forms\Components\TextInput::make('password')->password()->revealable()->required()->minLength(8),
            ])
            ->action(function (Client $record, array $data): void {
                abort_unless(static::staffCan('clients.manage'), 403);

                $record->update(['password' => $data['password']]);
                $record->tokens()->delete();
                Notification::make()->title('Password updated and sessions revoked')->success()->send();
            });
    }

    public static function revokeSessionsAction(): Actions\Action
    {
        return Actions\Action::make('revokeSessions')
            ->label('Revoke sessions')
            ->icon('heroicon-o-no-symbol')
            ->color('warning')
            ->requiresConfirmation()
            ->visible(fn () => static::staffCan('clients.manage'))
            ->action(function (Client $record): void {
                abort_unless(static::staffCan('clients.manage'), 403);

                $record->tokens()->delete();
                Notification::make()->title('Sessions revoked')->success()->send();
            });
    }

    public static function getRelations(): array
    {
        return [OrdersRelationManager::class];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListClients::route('/'),
            'edit' => Pages\EditClient::route('/{record}/edit'),
        ];
    }
}
