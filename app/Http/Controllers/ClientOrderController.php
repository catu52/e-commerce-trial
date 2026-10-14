<?php

namespace App\Http\Controllers;

use App\Http\Requests\ClientCheckoutRequest;
use App\Http\Resources\OrderResource;
use App\Services\CheckoutService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ClientOrderController extends Controller
{
    public function __construct(protected CheckoutService $checkout) {}

    public function store(ClientCheckoutRequest $request): JsonResponse
    {
        $order = $this->checkout->placeOrder($request->user(), $request->validated());

        return response()->json(['data' => (new OrderResource($order))->resolve()], 201);
    }

    public function index(Request $request): JsonResponse
    {
        $orders = $request->user()->orders()
            ->whereNotNull('order_number')
            ->with('items')
            ->latest()
            ->get();

        return response()->json(['data' => OrderResource::collection($orders)->resolve()]);
    }

    public function show(Request $request, string $orderNumber): JsonResponse
    {
        $order = $request->user()->orders()->with('items')->where('order_number', $orderNumber)->firstOrFail();

        return response()->json(['data' => (new OrderResource($order))->resolve()]);
    }
}
