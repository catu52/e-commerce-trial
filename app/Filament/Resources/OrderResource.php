<?php

namespace App\Filament\Resources;

use App\Enums\OrderStatus;
use App\Filament\Concerns\AuthorizesByPermission;
use App\Filament\Resources\OrderResource\Pages;
use App\Models\Order;
use App\Services\OrderCmsService;
use BackedEnum;
use Filament\Actions;
use Filament\Forms;
use Filament\Infolists;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use RuntimeException;
use UnitEnum;

class OrderResource extends Resource
{
    use AuthorizesByPermission;

    protected static ?string $model = Order::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-receipt-percent';

    protected static string|UnitEnum|null $navigationGroup = 'Sales';

    protected static ?string $recordTitleAttribute = 'order_number';

    protected static ?string $viewPermission = 'orders.view';

    protected static ?string $managePermission = null;

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
        return false;
    }

    public static function canDeleteAny(): bool
    {
        return false;
    }

    public static function canRefund(Order $order): bool
    {
        return $order->status !== OrderStatus::FAILED && self::staffCan('orders.refund');
    }

    /**
     * Shared refund action (table row + view page) delegating to OrderCmsService.
     */
    public static function refundAction(): Actions\Action
    {
        return Actions\Action::make('refund')
            ->label('Refund / cancel')
            ->icon('heroicon-o-arrow-uturn-left')
            ->color('danger')
            ->requiresConfirmation()
            ->visible(fn (Order $record) => self::canRefund($record))
            ->schema([
                Forms\Components\Textarea::make('reason')->required()->maxLength(500),
            ])
            ->action(function (Order $record, array $data): void {
                abort_unless(self::canRefund($record), 403);

                try {
                    app(OrderCmsService::class)->cancelAndRefundOrder($record, $data['reason'], (int) Auth::id());
                    $record->refresh();
                    Notification::make()->title('Order refunded and stock restored')->success()->send();
                } catch (RuntimeException $e) {
                    Notification::make()->title($e->getMessage())->danger()->send();
                }
            });
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with('client'))
            ->columns([
                Tables\Columns\TextColumn::make('order_number')->label('Order')->placeholder('#')->formatStateUsing(fn ($state, Order $record) => $state ?: '#'.$record->id)->searchable(),
                Tables\Columns\TextColumn::make('email')->searchable()->placeholder('—'),
                Tables\Columns\TextColumn::make('status')->badge()
                    ->formatStateUsing(fn ($state) => Str::headline($state instanceof OrderStatus ? $state->value : (string) $state))
                    ->color(fn ($state) => match ($state instanceof OrderStatus ? $state : OrderStatus::tryFrom((string) $state)) {
                        OrderStatus::COMPLETED => 'success',
                        OrderStatus::FAILED => 'danger',
                        OrderStatus::PROCESSING => 'info',
                        default => 'warning',
                    }),
                Tables\Columns\TextColumn::make('total_amount')->label('Total')->money(fn (Order $record) => $record->currency ?: 'EUR')->sortable(),
                Tables\Columns\TextColumn::make('created_at')->dateTime()->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')->options(
                    collect(OrderStatus::cases())->mapWithKeys(fn (OrderStatus $s) => [$s->value => Str::headline($s->value)])->all()
                ),
            ])
            ->recordActions([
                Actions\ViewAction::make(),
                self::refundAction(),
            ])
            ->defaultSort('id', 'desc');
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Order')->columns(3)->schema([
                Infolists\Components\TextEntry::make('order_number')->placeholder('—'),
                Infolists\Components\TextEntry::make('status')->badge()->formatStateUsing(fn ($state) => Str::headline($state instanceof OrderStatus ? $state->value : (string) $state)),
                Infolists\Components\TextEntry::make('created_at')->dateTime(),
                Infolists\Components\TextEntry::make('subtotal')->money(fn (Order $record) => $record->currency ?: 'EUR'),
                Infolists\Components\TextEntry::make('delivery_cost')->money(fn (Order $record) => $record->currency ?: 'EUR'),
                Infolists\Components\TextEntry::make('discount')->money(fn (Order $record) => $record->currency ?: 'EUR'),
                Infolists\Components\TextEntry::make('total_amount')->label('Total')->money(fn (Order $record) => $record->currency ?: 'EUR'),
                Infolists\Components\TextEntry::make('promo_code')->placeholder('—'),
                Infolists\Components\TextEntry::make('payment_method')->placeholder('—'),
            ]),
            Section::make('Customer & shipping')->columns(3)->schema([
                Infolists\Components\TextEntry::make('email')->placeholder('—'),
                Infolists\Components\TextEntry::make('first_name')->placeholder('—'),
                Infolists\Components\TextEntry::make('last_name')->placeholder('—'),
                Infolists\Components\TextEntry::make('phone')->placeholder('—'),
                Infolists\Components\TextEntry::make('delivery_method')->placeholder('—'),
                Infolists\Components\TextEntry::make('address')->placeholder('—'),
                Infolists\Components\TextEntry::make('city')->placeholder('—'),
                Infolists\Components\TextEntry::make('postal_code')->placeholder('—'),
                Infolists\Components\TextEntry::make('country')->placeholder('—'),
            ]),
            Section::make('Items')->schema([
                Infolists\Components\RepeatableEntry::make('items')->hiddenLabel()->columns(6)->schema([
                    Infolists\Components\TextEntry::make('title'),
                    Infolists\Components\TextEntry::make('sku')->placeholder('—'),
                    Infolists\Components\TextEntry::make('size')->placeholder('—'),
                    Infolists\Components\TextEntry::make('color')->placeholder('—'),
                    Infolists\Components\TextEntry::make('quantity'),
                    Infolists\Components\TextEntry::make('unit_price')->money(fn ($record) => $record->order?->currency ?: 'EUR'),
                ]),
            ]),
        ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListOrders::route('/'),
            'view' => Pages\ViewOrder::route('/{record}'),
        ];
    }
}
