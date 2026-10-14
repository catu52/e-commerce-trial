<?php

namespace App\Jobs;

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\Reservation;
use App\Services\FlashSaleReservationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

class ProcessOrderReservationJob implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * The number of times the job may be attempted.
     */
    public int $tries = 3;

    /**
     * The number of seconds the job can run before timing out.
     */
    public int $timeout = 30;

    /**
     * The number of seconds to wait before retrying the job.
     */
    public int $backoff = 5;

    /**
     * Indicate if the job should be marked as failed when timeout occurs.
     */
    public bool $failOnTimeout = true;

    /**
     * Create a new job instance.
     */
    public function __construct(
        public int $reservationId
    ) {}

    /**
     * Calculate unique ID for lock prevention during processing.
     */
    public function uniqueId(): string
    {
        return (string) $this->reservationId;
    }

    /**
     * Execute the job.
     */
    public function handle(FlashSaleReservationService $reservationService): void
    {
        Log::info("Processing order reservation job for Reservation #{$this->reservationId}");

        /** @var Reservation|null $reservation */
        $reservation = Reservation::with(['client', 'flashSale.product'])->find($this->reservationId);

        if (! $reservation) {
            Log::error("ProcessOrderReservationJob failed: Reservation #{$this->reservationId} not found.");

            return;
        }

        // Validate reservation status and expiration
        if (! $reservation->isReserved()) {
            Log::warning("Reservation #{$reservation->id} is not in a valid state for processing. Current status: {$reservation->status->value}");

            if ($reservation->isExpired()) {
                $reservationService->releaseReservation($reservation);
            }

            return;
        }

        // Execute Order creation within a strict database transaction
        DB::transaction(function () use ($reservation) {
            $flashSale = $reservation->flashSale;
            $product = $flashSale->product;
            $totalAmount = $flashSale->sale_price * $reservation->quantity;

            // 1. Create the permanent Order record
            $order = Order::create([
                'client_id' => $reservation->client_id,
                'product_id' => $product->id,
                'flash_sale_id' => $flashSale->id,
                'reservation_id' => $reservation->id,
                'quantity' => $reservation->quantity,
                'total_amount' => $totalAmount,
                'status' => OrderStatus::COMPLETED,
            ]);

            // 2. Transition Reservation state to completed
            $reservation->markAsCompleted();

            Log::info("Order #{$order->id} created successfully for Reservation #{$reservation->id}.", [
                'client_id' => $reservation->client_id,
                'total_amount' => $totalAmount,
            ]);
        });
    }

    /**
     * Handle job failure after max retries or unhandled exceptions.
     */
    public function failed(Throwable $exception): void
    {
        Log::error("ProcessOrderReservationJob failed permanently for Reservation #{$this->reservationId}: {$exception->getMessage()}", [
            'exception' => $exception,
        ]);

        /** @var Reservation|null $reservation */
        $reservation = Reservation::find($this->reservationId);

        if ($reservation && $reservation->isReserved()) {
            try {
                /** @var FlashSaleReservationService $reservationService */
                $reservationService = app(FlashSaleReservationService::class);
                $reservationService->releaseReservation($reservation);

                Log::notice("Released stock and cancelled Reservation #{$this->reservationId} due to worker failure.");
            } catch (Throwable $rollbackException) {
                Log::critical("Failed to release reservation #{$this->reservationId} on job failure: {$rollbackException->getMessage()}");
            }
        }
    }
}
