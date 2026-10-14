<?php

namespace App\Filament\Resources\ProductResource\RelationManagers;

use App\Models\Product;
use App\Models\ProductImage;
use App\Services\ProductImageService;
use Filament\Actions;
use Filament\Forms;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;

class ImagesRelationManager extends RelationManager
{
    protected static string $relationship = 'images';

    protected static ?string $title = 'Images';

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            Forms\Components\FileUpload::make('path')
                ->label('Image')
                ->image()
                ->disk('public')
                ->directory(fn () => 'products/'.$this->getOwnerRecord()->getKey())
                ->visibility('public')
                ->required(),
            Forms\Components\TextInput::make('position')->numeric()->integer()->minValue(0)->default(0),
            Forms\Components\Toggle::make('is_primary')->label('Primary'),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('path')
            ->columns([
                Tables\Columns\ImageColumn::make('path')->disk('public')->label('Image'),
                Tables\Columns\TextColumn::make('position')->sortable(),
                Tables\Columns\IconColumn::make('is_primary')->boolean()->label('Primary'),
            ])
            ->headerActions([
                Actions\CreateAction::make()->after(fn (ProductImage $record) => $this->normalizePrimary($record)),
            ])
            ->recordActions([
                Actions\EditAction::make()->after(fn (ProductImage $record) => $this->normalizePrimary($record)),
                Actions\Action::make('makePrimary')
                    ->icon('heroicon-o-star')
                    ->visible(fn (ProductImage $record) => ! $record->is_primary)
                    ->action(function (ProductImage $record): void {
                        $record->update(['is_primary' => true]);
                        $this->normalizePrimary($record);
                    }),
                Actions\DeleteAction::make()
                    ->using(fn (ProductImage $record, ProductImageService $service) => $service->delete($this->getOwnerRecord(), $record)),
            ])
            ->defaultSort('position');
    }

    /**
     * Keep exactly one primary image per product, matching ProductImageService behaviour.
     */
    private function normalizePrimary(ProductImage $image): void
    {
        /** @var Product $product */
        $product = $this->getOwnerRecord();

        if ($image->is_primary || ! $product->images()->where('is_primary', true)->exists()) {
            $product->images()->whereKeyNot($image->getKey())->update(['is_primary' => false]);
            $image->update(['is_primary' => true]);
        }
    }
}
