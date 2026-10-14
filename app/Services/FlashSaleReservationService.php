<?php

namespace App\Services;

use App\Enums\ReservationStatus;
use App\Models\Client;
use App\Models\FlashSale;
use App\Models\Reservation;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;
use RuntimeException;

class FlashSaleReservationService
{
    /**
     * Pre-load (warm) flash sale stock into Redis before the sale starts.
     */
    public function warmFlashSaleStock(FlashSale $flashSale): void
    {
        $stockKey = "flash_sale:{$flashSale->id}:stock";
        $activeKey = "flash_sale:{$flashSale->id}:active";

        // Store available stock atomically
        Redis::set($stockKey, $flashSale->available_stock);
        Redis::set($activeKey, $flashSale->is_active ? '1' : '0');

        Log::info("Warmed stock for Flash Sale #{$flashSale->id}: {$flashSale->available_stock} units.");
    }

    /**
     * Atomically reserve stock for a client using a Redis Lua script.
     * Prevents race conditions and guarantees zero overselling.
     */
    public function reserve(Client $client, FlashSale $flashSale, int $quantity = 1): Reservation
    {
        $stockKey = "flash_sale:{$flashSale->id}:stock";
        $userHoldKey = "flash_sale:{$flashSale->id}:client:{$client->id}";
        $maxPerClient = 1; // Limit 1 purchase per client for flash sale

        // Lua script ensures check-and-decrement runs atomically inside Redis
        $luaScript = <<<'LUA'
            local stockKey = KEYS[1]
            local userHoldKey = KEYS[2]
            local quantity = tonumber(ARGV[1])
            local maxPerClient = tonumber(ARGV[2])

            -- Check if client already holds stock
            local currentHold = tonumber(redis.call('get', userHoldKey) or "0")
            if (currentHold + quantity) > maxPerClient then
                return -1 -- Exceeds purchase limit
            end

            -- Check available stock
            local currentStock = tonumber(redis.call('get', stockKey) or "0")
            if currentStock < quantity then
                return -2 -- Out of stock
            end

            -- Decrement stock and mark client hold
            redis.call('decrby', stockKey, quantity)
            redis.call('incrby', userHoldKey, quantity)
            return 1 -- Success
        LUA;

        /** @var int $result */
        $result = Redis::eval($luaScript, 2, $stockKey, $userHoldKey, $quantity, $maxPerClient);

        if ($result === -1) {
            throw new RuntimeException("Purchase limit exceeded. Maximum {$maxPerClient} item(s) per client.");
        }

        if ($result === -2) {
            throw new RuntimeException('Flash sale is sold out!');
        }

        // Create reservation record in PostgreSQL inside database transaction
        return DB::transaction(function () use ($client, $flashSale, $quantity) {
            $reservation = Reservation::create([
                'client_id' => $client->id,
                'flash_sale_id' => $flashSale->id,
                'quantity' => $quantity,
                'status' => ReservationStatus::RESERVED,
                'expires_at' => now()->addMinutes(10), // 10-minute hold window
            ]);

            // Decrement DB available stock shadow counter
            $flashSale->decrement('available_stock', $quantity);

            return $reservation;
        });
    }

    /**
     * Restore stock to Redis and DB if a reservation expires or is cancelled.
     */
    public function releaseReservation(Reservation $reservation): void
    {
        if ($reservation->status !== ReservationStatus::RESERVED) {
            return;
        }

        DB::transaction(function () use ($reservation) {
            // Mark reservation as expired/cancelled
            $reservation->update(['status' => ReservationStatus::EXPIRED]);

            // Restore DB shadow inventory
            $reservation->flashSale->increment('available_stock', $reservation->quantity);

            // Restore Redis atomic stock count
            $stockKey = "flash_sale:{$reservation->flash_sale_id}:stock";
            $userHoldKey = "flash_sale:{$reservation->flash_sale_id}:client:{$reservation->client_id}";

            Redis::incrby($stockKey, $reservation->quantity);
            Redis::decrby($userHoldKey, $reservation->quantity);
        });

        Log::info("Released expired reservation #{$reservation->id} back to stock pool.");
    }
}
