<?php

namespace App\Filament\Resources\FlashSaleResource\Pages;

use App\Filament\Concerns\WarmsFlashSaleStock;
use App\Filament\Resources\FlashSaleResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditFlashSale extends EditRecord
{
    use WarmsFlashSaleStock;

    protected static string $resource = FlashSaleResource::class;

    protected function getHeaderActions(): array
    {
        return [Actions\DeleteAction::make()];
    }

    protected function afterSave(): void
    {
        $this->warmStock();
    }
}
