<?php

namespace App\Listeners;

use App\Events\ReservationCreatedEvent;
use App\Jobs\ProcessOrderReservationJob;
use Illuminate\Contracts\Queue\ShouldQueueAfterCommit;
use Illuminate\Support\Facades\Log;
use Throwable;

class ReservationTransactionListener implements ShouldQueueAfterCommit
{
    /**
     * Handle the event after the database transaction has committed.
     */
    public function handle(ReservationCreatedEvent $event): void
    {
        $reservation = $event->reservation;

        Log::info("Transaction committed for Reservation #{$reservation->id}. Dispatching order processing job to RabbitMQ.");

        ProcessOrderReservationJob::dispatch($reservation->id)
            ->onQueue('order.reservations');
    }

    /**
     * Handle listener failure.
     */
    public function failed(ReservationCreatedEvent $event, Throwable $exception): void
    {
        Log::error("Failed transaction listener for Reservation #{$event->reservation->id}: {$exception->getMessage()}");
    }
}
