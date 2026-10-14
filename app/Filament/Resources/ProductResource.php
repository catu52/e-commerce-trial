<?php

namespace App\Filament\Resources;

use App\Enums\ProductGender;
use App\Filament\Concerns\AuthorizesByPermission;
use App\Filament\Resources\ProductResource\Pages;
use App\Filament\Resources\ProductResource\RelationManagers\ImagesRelationManager;
use App\Models\Product;
use App\Services\ProductStockService;
use BackedEnum;
use Filament\Actions;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Text;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Str;
use InvalidArgumentException;
use UnitEnum;

class ProductResource extends Resource
{
    use AuthorizesByPermission;

    protected static ?string $model = Product::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-shopping-bag';

    protected static string|UnitEnum|null $navigationGroup = 'Catalog';

    protected static ?string $recordTitleAttribute = 'name';

    protected static ?string $viewPermission = 'products.manage';

    protected static ?string $managePermission = 'products.manage';

    /** @return array<string, string> */
    private static function genderOptions(): array
    {
        return collect(ProductGender::cases())->mapWithKeys(fn (ProductGender $g) => [$g->value => Str::headline($g->value)])->all();
    }

    public static function storefrontUrl(Product $product): string
    {
        return rtrim((string) config('shop.frontend_url'), '/').'/products/'.rawurlencode($product->slug);
    }

    /** Product name followed by an icon that opens its storefront page in a new tab. */
    private static function titleWithSiteLink(string $title, Product $product): HtmlString
    {
        $url = e(self::storefrontUrl($product));
        $icon = '<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" style="width:1rem;height:1rem" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M13.5 6H5.25A2.25 2.25 0 0 0 3 8.25v10.5A2.25 2.25 0 0 0 5.25 21h10.5A2.25 2.25 0 0 0 18 18.75V10.5m-10.5 6L21 3m0 0h-5.25M21 3v5.25"/></svg>';

        return new HtmlString(
            '<span style="display:inline-flex;align-items:center;gap:.4rem">'.e($title)
            .'<a href="'.$url.'" target="_blank" rel="noopener" title="View on site" aria-label="View '.e($title).' on site" onclick="event.stopPropagation()" style="display:inline-flex;opacity:.7">'.$icon.'</a></span>'
        );
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->columns(1)->components([
            Section::make('Catalog')->columnSpanFull()->columns(2)->schema([
                Forms\Components\TextInput::make('name')
                    ->required()
                    ->maxLength(255)
                    ->live(onBlur: true)
                    ->afterStateUpdated(fn (Set $set, ?string $state, string $operation) => $operation === 'create' ? $set('slug', Str::slug((string) $state)) : null),
                Forms\Components\TextInput::make('slug')->required()->maxLength(255)->unique(ignoreRecord: true),
                Forms\Components\TextInput::make('sku')->label('SKU')->required()->maxLength(255)->unique(ignoreRecord: true),
                Forms\Components\TextInput::make('code')->maxLength(255),
                Forms\Components\Select::make('category_id')->label('Category')->relationship('category', 'name')->searchable()->preload(),
                Forms\Components\TextInput::make('sub_category')->maxLength(255),
                Forms\Components\Select::make('gender')->options(self::genderOptions())->default(ProductGender::UNISEX->value)->required()->native(false),
                Forms\Components\TextInput::make('tag')->maxLength(255),
                Forms\Components\TextInput::make('badge')->maxLength(255),
                Forms\Components\Toggle::make('is_featured')->label('Featured'),
                Forms\Components\Textarea::make('description')->columnSpanFull()->rows(3),
            ]),
            Section::make('Pricing & stock')->columnSpanFull()->columns(3)->schema([
                Forms\Components\TextInput::make('currency')->required()->default('EUR')->length(3)->dehydrateStateUsing(fn (?string $state) => strtoupper((string) $state)),
                Forms\Components\TextInput::make('base_price')->numeric()->required()->minValue(0)->step(0.01),
                Forms\Components\TextInput::make('stock_quantity')->numeric()->integer()->required()->minValue(0)->default(0),
            ]),
            Section::make('Sale')->columnSpanFull()->columns(3)->schema([
                Forms\Components\Toggle::make('on_sale')
                    ->label('On sale')
                    ->live()
                    ->dehydrated(false)
                    ->afterStateHydrated(fn (Forms\Components\Toggle $component, ?Model $record) => $component->state(filled($record?->sale_discount_percent)))
                    ->columnSpanFull(),
                Forms\Components\TextInput::make('sale_discount_percent')
                    ->label('Discount %')
                    ->numeric()->integer()->minValue(1)->maxValue(90)->suffix('%')
                    ->live(onBlur: true)
                    ->visible(fn (Get $get) => (bool) $get('on_sale'))
                    ->required(fn (Get $get) => (bool) $get('on_sale'))
                    ->dehydratedWhenHidden()
                    ->dehydrateStateUsing(fn ($state, Get $get) => $get('on_sale') ? $state : null),
                Forms\Components\DateTimePicker::make('sale_starts_at')
                    ->label('Starts at')
                    ->visible(fn (Get $get) => (bool) $get('on_sale'))
                    ->dehydratedWhenHidden()
                    ->dehydrateStateUsing(fn ($state, Get $get) => $get('on_sale') ? $state : null),
                Forms\Components\DateTimePicker::make('sale_ends_at')
                    ->label('Ends at')
                    ->after('sale_starts_at')
                    ->visible(fn (Get $get) => (bool) $get('on_sale'))
                    ->dehydratedWhenHidden()
                    ->dehydrateStateUsing(fn ($state, Get $get) => $get('on_sale') ? $state : null),
                Text::make(function (Get $get): string {
                    $base = (float) $get('base_price');
                    $percent = (int) $get('sale_discount_percent');
                    if ($base <= 0 || $percent < 1 || $percent > 90) {
                        return 'Enter a base price and a discount to preview the sale price.';
                    }

                    return sprintf('Sale price: %s %s (was %s)', number_format(round($base * (100 - $percent) / 100, 2), 2), $get('currency') ?: 'EUR', number_format($base, 2));
                })
                    ->visible(fn (Get $get) => (bool) $get('on_sale'))
                    ->columnSpanFull(),
            ]),
            Section::make('Attributes')->columnSpanFull()->columns(2)->schema([
                Forms\Components\TextInput::make('substrate')->maxLength(255),
                Forms\Components\TextInput::make('spec_weight')->maxLength(255),
                Forms\Components\TextInput::make('colorway')->maxLength(255),
                Forms\Components\TagsInput::make('sizes')->placeholder('Add size'),
                Forms\Components\Repeater::make('colors')
                    ->label('Colors')
                    ->schema([
                        Forms\Components\TextInput::make('name')->required()->maxLength(100),
                        Forms\Components\ColorPicker::make('hex')->label('Color')->required()->regex('/^#[0-9a-fA-F]{3,8}$/'),
                    ])
                    ->columns(2)
                    ->defaultItems(0)
                    ->addActionLabel('Add color')
                    ->itemLabel(fn (array $state): ?string => $state['name'] ?? null)
                    ->afterStateHydrated(function (Forms\Components\Repeater $component, $state): void {
                        // Legacy rows saved as plain strings by the old tags input
                        $component->state(collect($state ?? [])->map(
                            fn ($color) => is_array($color) ? $color : ['name' => (string) $color, 'hex' => '#000000']
                        )->values()->all());
                    })
                    ->collapsible()
                    ->columnSpanFull(),
                Forms\Components\KeyValue::make('specs')->columnSpanFull(),
            ]),
            Section::make('Editorial & SEO')->columnSpanFull()->columns(2)->collapsed()->schema([
                Forms\Components\TextInput::make('headline')->maxLength(255)->columnSpanFull(),
                Forms\Components\Textarea::make('long_description')->rows(5)->columnSpanFull(),
                Forms\Components\Textarea::make('care_instructions')->rows(3)->columnSpanFull(),
                Forms\Components\TextInput::make('seo_title')->maxLength(255),
                Forms\Components\Textarea::make('seo_description')->maxLength(320)->rows(2),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with('category'))
            ->columns([
                Tables\Columns\TextColumn::make('sku')->label('SKU')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('name')
                    ->searchable()
                    ->sortable()
                    ->html()
                    ->formatStateUsing(fn (string $state, Product $record): HtmlString => self::titleWithSiteLink($state, $record)),
                Tables\Columns\TextColumn::make('category.name')->sortable(),
                Tables\Columns\TextColumn::make('gender')->badge()->formatStateUsing(fn ($state) => Str::headline($state instanceof ProductGender ? $state->value : (string) $state)),
                Tables\Columns\ColorColumn::make('colors')
                    ->getStateUsing(fn (Product $record): array => collect($record->colors ?? [])->pluck('hex')->filter()->values()->all())
                    ->tooltip(fn (Product $record): ?string => collect($record->colors ?? [])->pluck('name')->filter()->implode(', ') ?: null)
                    ->wrap(),
                Tables\Columns\TextColumn::make('base_price')->money(fn (Product $record) => $record->currency ?: 'EUR')->sortable(),
                Tables\Columns\TextColumn::make('sale_discount_percent')
                    ->label('Sale')
                    ->badge()
                    ->color('danger')
                    ->getStateUsing(fn (Product $record) => $record->isOnSale() ? '-'.$record->sale_discount_percent.'%' : null)
                    ->description(fn (Product $record) => $record->isOnSale() ? number_format($record->salePrice(), 2).' '.($record->currency ?: 'EUR') : null)
                    ->sortable(),
                Tables\Columns\TextColumn::make('stock_quantity')->label('Stock')->sortable()
                    ->color(fn (int $state) => $state <= (int) config('shop.low_stock_threshold', 5) ? 'danger' : null),
                Tables\Columns\IconColumn::make('is_featured')->boolean()->label('Featured'),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('category_id')->label('Category')->relationship('category', 'name'),
                Tables\Filters\SelectFilter::make('gender')->options(self::genderOptions()),
                Tables\Filters\TernaryFilter::make('is_featured')->label('Featured'),
                Tables\Filters\TernaryFilter::make('on_sale')->label('On sale')
                    ->queries(
                        true: fn (Builder $query) => $query->onSale(),
                        false: fn (Builder $query) => $query->whereNotIn('products.id', Product::onSale()->select('products.id')),
                    ),
                Tables\Filters\SelectFilter::make('discount_tier')->label('Discount tier')
                    ->options(['10' => '10% or more', '20' => '20% or more', '30' => '30% or more', '50' => '50% or more'])
                    ->query(fn (Builder $query, array $data) => filled($data['value'] ?? null) ? $query->onSale((int) $data['value']) : $query),
                Tables\Filters\Filter::make('low_stock')->label('Low stock')
                    ->query(fn (Builder $query) => $query->where('stock_quantity', '<=', (int) config('shop.low_stock_threshold', 5))),
            ])
            ->recordActions([
                Actions\EditAction::make(),
                Actions\Action::make('adjustStock')
                    ->label('Adjust stock')
                    ->icon('heroicon-o-arrows-up-down')
                    ->visible(fn () => self::staffCan(static::$managePermission))
                    ->schema([
                        Forms\Components\Select::make('type')->options(['add' => 'Add', 'subtract' => 'Subtract', 'set' => 'Set to'])->required()->default('add'),
                        Forms\Components\TextInput::make('quantity')->numeric()->integer()->minValue(0)->required(),
                        Forms\Components\TextInput::make('reason')->required()->maxLength(255),
                    ])
                    ->action(function (Product $record, array $data, ProductStockService $stock): void {
                        try {
                            $stock->adjustStock($record, $data['type'], (int) $data['quantity'], $data['reason'], (int) Auth::id());
                            Notification::make()->title('Stock updated')->success()->send();
                        } catch (InvalidArgumentException $e) {
                            Notification::make()->title($e->getMessage())->danger()->send();
                        }
                    }),
            ])
            ->toolbarActions([
                Actions\BulkActionGroup::make([
                    Actions\BulkAction::make('putOnSale')
                        ->label('Put on sale')
                        ->icon('heroicon-o-tag')
                        ->visible(fn () => self::staffCan(static::$managePermission))
                        ->schema([
                            Forms\Components\TextInput::make('percent')->label('Discount %')->numeric()->integer()->minValue(1)->maxValue(90)->required()->suffix('%'),
                            Forms\Components\DateTimePicker::make('starts_at')->label('Starts at'),
                            Forms\Components\DateTimePicker::make('ends_at')->label('Ends at')->after('starts_at'),
                        ])
                        ->action(function (Collection $records, array $data): void {
                            self::authorizeManage();
                            Product::whereKey($records->modelKeys())->update([
                                'sale_discount_percent' => (int) $data['percent'],
                                'sale_starts_at' => $data['starts_at'] ?? null,
                                'sale_ends_at' => $data['ends_at'] ?? null,
                            ]);
                            Notification::make()->title($records->count().' product(s) put on sale')->success()->send();
                        })
                        ->deselectRecordsAfterCompletion(),
                    Actions\BulkAction::make('endSale')
                        ->label('End sale')
                        ->icon('heroicon-o-x-circle')
                        ->color('gray')
                        ->requiresConfirmation()
                        ->visible(fn () => self::staffCan(static::$managePermission))
                        ->action(function (Collection $records): void {
                            self::authorizeManage();
                            Product::whereKey($records->modelKeys())->update([
                                'sale_discount_percent' => null,
                                'sale_starts_at' => null,
                                'sale_ends_at' => null,
                            ]);
                            Notification::make()->title('Sale ended')->success()->send();
                        })
                        ->deselectRecordsAfterCompletion(),
                ]),
            ])
            ->defaultSort('id', 'desc');
    }

    private static function authorizeManage(): void
    {
        abort_unless(self::staffCan(static::$managePermission), 403);
    }

    public static function getRelations(): array
    {
        return [ImagesRelationManager::class];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListProducts::route('/'),
            'create' => Pages\CreateProduct::route('/create'),
            'edit' => Pages\EditProduct::route('/{record}/edit'),
        ];
    }
}
