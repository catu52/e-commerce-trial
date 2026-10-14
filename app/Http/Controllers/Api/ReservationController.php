<?php

namespace App\Http\Controllers\Api;

use App\Events\ReservationCreatedEvent;
use App\Http\Controllers\Controller;
use App\Models\FlashSale;
use App\Services\FlashSaleReservationService;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ReservationController extends Controller
{
    public function __construct(
        protected FlashSaleReservationService $reservationService
    ) {}

    /**
     * Reserve an item in a flash sale (High Concurrency Endpoint)
     */
    public function store(Request $request, int $flashSaleId): JsonResponse
    {
        $request->validate([
            'quantity' => 'nullable|integer|min:1|max:1',
        ]);

        $quantity = $request->input('quantity', 1);
        $client = $request->user(); // Authenticated Client model

        $flashSale = FlashSale::where('is_active', true)
            ->where('starts_at', '<=', now())
            ->where('ends_at', '>=', now())
            ->findOrFail($flashSaleId);

        try {
            // 1. Perform atomic reservation in Redis & create Reservation record
            $reservation = $this->reservationService->reserve($client, $flashSale, $quantity);

            // 2. Fire ReservationCreatedEvent; the ReservationTransactionListener
            //    (ShouldQueueAfterCommit) dispatches the processing job onto
            //    RabbitMQ only after this request's DB transaction commits.
            event(new ReservationCreatedEvent($reservation));

            // 3. Return immediate 202 Accepted response to React SPA
            return response()->json([
                'status' => 'processing',
                'message' => 'Stock successfully reserved! Processing your order.',
                'reservation' => [
                    'id' => $reservation->id,
                    'status' => $reservation->status,
                    'expires_at' => $reservation->expires_at->toIso8601String(),
                ],
            ], 202);
        } catch (Exception $e) {
            return response()->json([
                'status' => 'failed',
                'message' => $e->getMessage(),
            ], 422);
        }
    }
}
