<?php

namespace App\Filament\Concerns;

use App\Services\FlashSaleReservationService;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Re-syncs the Redis stock counters after a flash sale is saved in the panel.
 */
trait WarmsFlashSaleStock
{
    protected function warmStock(): void
    {
        try {
            app(FlashSaleReservationService::class)->warmFlashSaleStock($this->getRecord());
        } catch (Throwable $e) {
            Log::warning('Could not warm flash sale stock in Redis', ['error' => $e->getMessage()]);
        }
    }
}
