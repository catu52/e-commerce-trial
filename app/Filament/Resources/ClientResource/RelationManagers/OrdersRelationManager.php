<?php

namespace App\Filament\Resources\ClientResource\RelationManagers;

use App\Enums\OrderStatus;
use App\Filament\Resources\OrderResource;
use App\Models\Order;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class OrdersRelationManager extends RelationManager
{
    protected static string $relationship = 'orders';

    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        return OrderResource::staffCan('orders.view');
    }

    public function isReadOnly(): bool
    {
        return true;
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('id')
            ->columns([
                Tables\Columns\TextColumn::make('order_number')->label('Order')
                    ->formatStateUsing(fn ($state, Order $record) => $state ?: '#'.$record->id),
                Tables\Columns\TextColumn::make('status')->badge()
                    ->formatStateUsing(fn ($state) => Str::headline($state instanceof OrderStatus ? $state->value : (string) $state)),
                Tables\Columns\TextColumn::make('total_amount')->label('Total')->money(fn (Order $record) => $record->currency ?: 'EUR'),
                Tables\Columns\TextColumn::make('created_at')->dateTime(),
            ])
            ->recordUrl(fn (Order $record) => OrderResource::getUrl('view', ['record' => $record]))
            ->defaultSort('id', 'desc');
    }
}
