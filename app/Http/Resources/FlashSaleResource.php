<?php

namespace App\Http\Resources;

use App\Models\FlashSale;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin FlashSale */
class FlashSaleResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $product = $this->product;
        $image = $product->relationLoaded('images') ? $product->images->first() : $product->images()->first();

        return [
            'id' => (string) $this->id,
            'status' => $this->isCurrentlyActive() ? 'active' : 'upcoming',
            'startsAt' => $this->starts_at->toIso8601String(),
            'endsAt' => $this->ends_at->toIso8601String(),
            'salePrice' => (float) $this->sale_price,
            'originalPrice' => (float) $product->base_price,
            'currency' => $product->currency,
            'discountPercent' => $this->discount_percent,
            'totalStock' => $this->total_stock,
            'availableStock' => $this->available_stock,
            'depletionPercent' => $this->depletion_percent,
            'stockState' => $this->stock_state,
            'product' => [
                'id' => (string) $product->id,
                'slug' => $product->slug,
                'title' => $product->name,
                'code' => $product->code,
                'category' => $product->category ? strtoupper($product->category->name) : null,
                'image' => $image ? url($image->url()) : null,
                'sizes' => $product->sizes ?? [],
                'sku' => $product->sku,
                'gender' => $product->gender?->value ?? 'unisex',
            ],
        ];
    }
}
