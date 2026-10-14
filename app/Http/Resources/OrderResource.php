<?php

namespace App\Http\Resources;

use App\Models\Order;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Order */
class OrderResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'orderNumber' => $this->order_number,
            'status' => $this->status->value,
            'subtotal' => $this->subtotal,
            'deliveryCost' => $this->delivery_cost,
            'discount' => $this->discount,
            'total' => $this->total_amount,
            'currency' => $this->currency,
            'deliveryMethod' => $this->delivery_method,
            'email' => $this->email,
            'items' => $this->items->map(fn ($item) => [
                'productId' => (string) $item->product_id,
                'title' => $item->title,
                'sku' => $item->sku,
                'size' => $item->size,
                'color' => $item->color,
                'quantity' => $item->quantity,
                'unitPrice' => $item->unit_price,
                'lineTotal' => round($item->unit_price * $item->quantity, 2),
            ])->values(),
            'createdAt' => $this->created_at?->toIso8601String(),
        ];
    }
}
