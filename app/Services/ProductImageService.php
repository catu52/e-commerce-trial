<?php

namespace App\Services;

use App\Models\Product;
use App\Models\ProductImage;
use App\Support\PublicImageUrl;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

class ProductImageService
{
    private const DISK = PublicImageUrl::DISK;

    public function upload(Product $product, UploadedFile $file, bool $primary): ProductImage
    {
        $path = $file->store("products/{$product->id}", self::DISK);
        $makePrimary = $primary || ! $product->images()->exists();

        if ($makePrimary) {
            $product->images()->update(['is_primary' => false]);
        }

        return $product->images()->create([
            'path' => $path,
            'position' => ((int) $product->images()->max('position')) + 1,
            'is_primary' => $makePrimary,
        ]);
    }

    public function delete(Product $product, ProductImage $image): void
    {
        Storage::disk(self::DISK)->delete($image->path);
        $wasPrimary = $image->is_primary;
        $image->delete();

        // Promote the next image so the product always has a primary one.
        if ($wasPrimary) {
            $product->images()->first()?->update(['is_primary' => true]);
        }
    }
}
