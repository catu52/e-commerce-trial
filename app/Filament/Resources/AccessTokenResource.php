<?php

namespace App\Filament\Resources;

use App\Filament\Resources\AccessTokenResource\Pages;
use App\Models\Client;
use App\Models\User;
use BackedEnum;
use Filament\Actions;
use Filament\Facades\Filament;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Laravel\Sanctum\PersonalAccessToken;
use UnitEnum;

/**
 * Admin-only management of Sanctum personal access tokens (staff and client).
 * Token hashes are never shown; a new plain-text token is displayed once at creation.
 */
class AccessTokenResource extends Resource
{
    protected static ?string $model = PersonalAccessToken::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-key';

    protected static string|UnitEnum|null $navigationGroup = 'Security';

    protected static ?string $navigationLabel = 'Access tokens';

    protected static ?string $modelLabel = 'access token';

    public static function isAdmin(): bool
    {
        $user = Filament::auth()->user();

        return $user instanceof User && $user->hasRole('admin');
    }

    public static function canViewAny(): bool
    {
        return static::isAdmin();
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit(Model $record): bool
    {
        return false;
    }

    public static function canDelete(Model $record): bool
    {
        return static::isAdmin();
    }

    public static function canDeleteAny(): bool
    {
        return static::isAdmin();
    }

    public static function ownerType(?string $class): string
    {
        return match ($class) {
            User::class => 'Staff',
            Client::class => 'Client',
            default => class_basename((string) $class),
        };
    }

    /** @return array<int|string, string> */
    public static function staffOptions(): array
    {
        return User::query()->orderBy('name')->get()->mapWithKeys(fn (User $u) => [$u->id => "{$u->name} ({$u->email})"])->all();
    }

    public static function createTokenForStaff(array $data): string
    {
        $staff = User::findOrFail($data['user_id']);
        $expires = filled($data['expires_at'] ?? null) ? Carbon::parse($data['expires_at']) : null;
        $abilities = $data['abilities'] ?: ['staff'];

        return $staff->createToken($data['name'], $abilities, $expires)->plainTextToken;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with('tokenable'))
            ->columns([
                Tables\Columns\TextColumn::make('name')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('tokenable_type')
                    ->label('Type')
                    ->badge()
                    ->formatStateUsing(fn (?string $state) => self::ownerType($state))
                    ->sortable(),
                Tables\Columns\TextColumn::make('tokenable.name')->label('Owner')->placeholder('—'),
                Tables\Columns\TextColumn::make('tokenable.email')->label('Email')->placeholder('—')->searchable(query: fn (Builder $query, string $search) => $query->whereHasMorph(
                    'tokenable',
                    [User::class, Client::class],
                    fn (Builder $q) => $q->where('email', 'like', "%{$search}%"),
                )),
                Tables\Columns\TextColumn::make('abilities')->badge()->separator(',')->limitList(3),
                Tables\Columns\TextColumn::make('last_used_at')->since()->placeholder('Never')->sortable(),
                Tables\Columns\TextColumn::make('expires_at')->dateTime()->placeholder('Never')->sortable()
                    ->color(fn (PersonalAccessToken $record) => $record->expires_at?->isPast() ? 'danger' : null),
                Tables\Columns\TextColumn::make('created_at')->dateTime()->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('tokenable_type')->label('Type')->options([
                    User::class => 'Staff',
                    Client::class => 'Client',
                ]),
                Tables\Filters\Filter::make('expired')->label('Expired')
                    ->query(fn (Builder $query) => $query->whereNotNull('expires_at')->where('expires_at', '<', now())),
                Tables\Filters\Filter::make('unused')->label('Never used')
                    ->query(fn (Builder $query) => $query->whereNull('last_used_at')),
            ])
            ->recordActions([
                Actions\DeleteAction::make()
                    ->label('Revoke')
                    ->icon('heroicon-o-no-symbol')
                    ->modalHeading('Revoke token')
                    ->modalDescription('The owner will be signed out of this token immediately.')
                    ->successNotificationTitle('Token revoked'),
            ])
            ->toolbarActions([
                Actions\BulkActionGroup::make([
                    Actions\DeleteBulkAction::make()->label('Revoke selected'),
                ]),
            ])
            ->defaultSort('created_at', 'desc');
    }

    public static function formSchema(): array
    {
        return [
            Forms\Components\Select::make('user_id')->label('Staff user')->options(fn () => self::staffOptions())->searchable()->required(),
            Forms\Components\TextInput::make('name')->label('Token name')->required()->maxLength(255),
            Forms\Components\TagsInput::make('abilities')->placeholder('Add ability')->default(['staff'])->helperText('Defaults to "staff", the ability the staff API expects.'),
            Forms\Components\DateTimePicker::make('expires_at')->label('Expires at')->minDate(now())->helperText('Leave empty for a token that never expires.'),
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListAccessTokens::route('/'),
        ];
    }
}
