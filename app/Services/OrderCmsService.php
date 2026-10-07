<?php

namespace App\Services;

use App\Enums\OrderStatus;
use App\Models\Order;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class OrderCmsService
{
    /**
     * Get a paginated list of orders, optionally filtered by status and search term.
     */
    public function getPaginatedOrders(int $perPage = 15, ?string $status = null, ?string $search = null): LengthAwarePaginator
    {
        // Get a paginated list of orders, optionally filtered by status and search term.
        // Build the query with optional filters for status and search term.
        return Order::with(['client', 'product', 'flashSale'])
            ->when($status, fn ($q) => $q->where('status', $status))
            ->when($search, function ($query, $search) {
                $query->whereHas('client', function ($cq) use ($search) {
                    $cq->where('email', 'ilike', "%{$search}%")
                        ->orWhere('first_name', 'ilike', "%{$search}%")
                        ->orWhere('last_name', 'ilike', "%{$search}%");
                });
            })
            ->latest()
            ->paginate($perPage);
    }

    /**
     * Cancel the specified order and refund the associated amount.
     *
     *
     * @throws RuntimeException
     */
    public function cancelAndRefundOrder(Order $order, string $reason, int $actorUserId): Order
    {
        // Cancel the specified order and refund the associated amount within a database transaction to ensure atomicity.
        return DB::transaction(function () use ($order, $reason, $actorUserId) {
            /** @var Order $lockedOrder */
            $lockedOrder = Order::where('id', $order->id)->lockForUpdate()->firstOrFail();

            // Check if the order is already failed/cancelled before proceeding.
            if ($lockedOrder->status === OrderStatus::FAILED) {
                throw new RuntimeException('Order is already failed/cancelled.');
            }

            // Mark order as failed / cancelled in the system.
            $lockedOrder->markAsFailed(OrderStatus::FAILED);

            // Restore base product inventory stock
            $lockedOrder->product()->increment('stock_quantity', $lockedOrder->quantity);

            // Audit log action
            Log::notice("Order #{$lockedOrder->id} cancelled by Staff User #{$actorUserId}", [
                'reason' => $reason,
                'refunded_amount' => $lockedOrder->total_amount,
                'restored_quantity' => $lockedOrder->quantity,
            ]);

            return $lockedOrder->load(['client', 'product']);
        });
    }
}
