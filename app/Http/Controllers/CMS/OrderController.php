<?php

namespace App\Http\Controllers\CMS;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Services\OrderCmsService;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OrderController extends Controller
{
    /**
     * OrderController constructor.
     *
     * @param  \App\Services\OrderCmsService  $orderService
     */
    public function __construct(protected OrderCmsService $orderService) {}

    /**
     * Display a paginated list of orders.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function index(Request $request): JsonResponse
    {
        // Check if the user has permission to view orders.
        if (! $request->user()->hasPermission('orders.view')) {
            return response()->json(['message' => 'Unauthorized.'], 403);
        }

        // Retrieve paginated list of orders based on query parameters.
        $orders = $this->orderService->getPaginatedOrders(
            perPage: (int) $request->query('per_page', 15),
            status: $request->query('status'),
            search: $request->query('search')
        );

        return response()->json($orders);
    }

    /**
     * Display the specified order.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Models\Order  $order
     * @return \Illuminate\Http\JsonResponse
     */
    public function show(Request $request, Order $order): JsonResponse
    {
        // Check if the user has permission to view the specified order.
        if (! $request->user()->hasPermission('orders.view')) {
            return response()->json(['message' => 'Unauthorized.'], 403);
        }

        return response()->json([
            'order' => $order->load(['client', 'product', 'flashSale', 'reservation']),
        ]);
    }

    /**
     * Refund the specified order.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Models\Order  $order
     * @return \Illuminate\Http\JsonResponse
     */
    public function refund(Request $request, Order $order): JsonResponse
    {
        // Check if the user has permission to refund the specified order.
        if (! $request->user()->hasPermission('orders.refund')) {
            return response()->json(['message' => 'Unauthorized.'], 403);
        }

        $request->validate([
            'reason' => ['required', 'string', 'max:255'],
        ]);

        // Attempt to cancel and refund the order.
        try {
            // Call the order service to cancel and refund the order.
            $cancelledOrder = $this->orderService->cancelAndRefundOrder(
                order: $order,
                reason: $request->input('reason'),
                actorUserId: $request->user()->id
            );

            return response()->json([
                'message' => 'Order cancelled and stock restored successfully.',
                'order' => $cancelledOrder,
            ]);
        } catch (Exception $e) { // Handle any exceptions that occur during order cancellation and refund.
            return response()->json([
                'message' => $e->getMessage(),
            ], 422);
        }
    }
}
