<?php

namespace App\Console\Commands;

use App\Models\FlashSale;
use App\Services\FlashSaleReservationService;
use Illuminate\Console\Command;

class WarmFlashSales extends Command
{
    protected $signature = 'flash-sales:warm';

    protected $description = 'Warm Redis stock counters for all current and upcoming flash sales';

    public function handle(FlashSaleReservationService $service): int
    {
        $sales = FlashSale::where('ends_at', '>=', now())->get();
        $sales->each(fn (FlashSale $sale) => $service->warmFlashSaleStock($sale));

        $this->info("Warmed {$sales->count()} flash sale(s).");

        return self::SUCCESS;
    }
}
