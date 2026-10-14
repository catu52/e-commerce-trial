<?php

namespace App\Filament\Resources;

use App\Filament\Concerns\AuthorizesByPermission;
use App\Filament\Resources\FlashSaleResource\Pages;
use App\Models\FlashSale;
use BackedEnum;
use Filament\Actions;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

class FlashSaleResource extends Resource
{
    use AuthorizesByPermission;

    protected static ?string $model = FlashSale::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-bolt';

    protected static string|UnitEnum|null $navigationGroup = 'Sales';

    protected static ?string $viewPermission = 'flash-sales.view';

    protected static ?string $managePermission = 'flash-sales.update';

    public static function canCreate(): bool
    {
        return static::staffCan('flash-sales.create');
    }

    public static function canEdit(Model $record): bool
    {
        return static::staffCan('flash-sales.update');
    }

    public static function canDelete(Model $record): bool
    {
        return static::staffCan('flash-sales.delete');
    }

    public static function canDeleteAny(): bool
    {
        return static::staffCan('flash-sales.delete');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Forms\Components\Select::make('product_id')->label('Product')->relationship('product', 'name')->searchable()->preload()->required(),
            Forms\Components\TextInput::make('sale_price')->numeric()->required()->minValue(0)->step(0.01),
            Forms\Components\TextInput::make('total_stock')->numeric()->integer()->required()->minValue(0),
            Forms\Components\TextInput::make('available_stock')->numeric()->integer()->required()->minValue(0)->lte('total_stock'),
            Forms\Components\DateTimePicker::make('starts_at')->required(),
            Forms\Components\DateTimePicker::make('ends_at')->required()->after('starts_at'),
            Forms\Components\Toggle::make('is_active')->default(true),
        ])->columns(2);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with('product'))
            ->columns([
                Tables\Columns\TextColumn::make('product.name')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('sale_price')->money('EUR')->sortable(),
                Tables\Columns\TextColumn::make('available_stock')->label('Available')->sortable(),
                Tables\Columns\TextColumn::make('total_stock')->label('Total')->sortable(),
                Tables\Columns\TextColumn::make('starts_at')->dateTime()->sortable(),
                Tables\Columns\TextColumn::make('ends_at')->dateTime()->sortable(),
                Tables\Columns\IconColumn::make('is_active')->boolean(),
            ])
            ->filters([
                Tables\Filters\TernaryFilter::make('is_active'),
                Tables\Filters\Filter::make('live')->label('Live now')->query(fn (Builder $query) => $query->active()),
                Tables\Filters\Filter::make('upcoming')->query(fn (Builder $query) => $query->upcoming()),
                Tables\Filters\Filter::make('expired')->query(fn (Builder $query) => $query->expired()),
            ])
            ->recordActions([
                Actions\EditAction::make(),
                Actions\DeleteAction::make(),
            ])
            ->defaultSort('starts_at', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListFlashSales::route('/'),
            'create' => Pages\CreateFlashSale::route('/create'),
            'edit' => Pages\EditFlashSale::route('/{record}/edit'),
        ];
    }
}
