<?php

namespace App\Filament\Resources\FlashSaleResource\Pages;

use App\Filament\Concerns\WarmsFlashSaleStock;
use App\Filament\Resources\FlashSaleResource;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Facades\Auth;

class CreateFlashSale extends CreateRecord
{
    use WarmsFlashSaleStock;

    protected static string $resource = FlashSaleResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['created_by'] = Auth::id();

        return $data;
    }

    protected function afterCreate(): void
    {
        $this->warmStock();
    }
}
