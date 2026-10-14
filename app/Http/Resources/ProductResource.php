<?php

namespace App\Http\Resources;

use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Product */
class ProductResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $colors = $this->colors ?? [];
        $images = $this->relationLoaded('images') ? $this->images : $this->images()->get();
        $urls = $images->map(fn ($image) => $image->url())->values();
        $onSale = $this->isOnSale();
        $price = $this->salePrice();

        return [
            'id' => (string) $this->id,
            'slug' => $this->slug,
            'title' => $this->name,
            'code' => $this->code,
            'gender' => $this->gender?->value ?? 'unisex',
            'category' => $this->category ? strtoupper($this->category->name) : null,
            'subCategory' => $this->sub_category,
            'priceEUR' => $price,
            'price' => $price,
            'currency' => $this->currency,
            'onSale' => $onSale,
            'discountPercent' => $onSale ? (int) $this->sale_discount_percent : null,
            'compareAtPriceEUR' => $onSale ? (float) $this->base_price : null,
            'saleEndsAt' => $onSale ? $this->sale_ends_at?->toIso8601String() : null,
            'specWeight' => $this->spec_weight,
            'substrate' => $this->substrate,
            'tag' => $this->tag,
            'badge' => $this->badge,
            'colorway' => $this->colorway,
            'colorHexList' => array_values(array_column($colors, 'hex')),
            'colorsAvailable' => array_values($colors),
            'sizes' => $this->sizes ?? [],
            'inStock' => $this->stock_quantity > 0,
            'stockLeft' => $this->stock_quantity,
            'featured' => (bool) $this->is_featured,
            'image' => $urls->first(),
            'galleryImages' => $urls->all(),
            'description' => $this->description,
            'headline' => $this->headline ?: null,
            'longDescription' => $this->long_description ?: null,
            'careInstructions' => $this->care_instructions ?: null,
            'seoTitle' => $this->seo_title ?: null,
            'seoDescription' => $this->seo_description ?: null,
            'sku' => $this->sku,
            'specs' => [
                'origin' => $this->specs['origin'] ?? null,
                'weight' => $this->specs['weight'] ?? null,
                'composition' => $this->specs['composition'] ?? null,
                'care' => $this->specs['care'] ?? null,
            ],
        ];
    }
}
