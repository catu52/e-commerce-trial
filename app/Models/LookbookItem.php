<?php

namespace App\Models;

use App\Support\PublicImageUrl;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['home_page_id', 'title', 'caption', 'link', 'image_path', 'sort_order'])]
class LookbookItem extends Model
{
    protected function casts(): array
    {
        return ['sort_order' => 'integer'];
    }

    public function homePage(): BelongsTo
    {
        return $this->belongsTo(HomePage::class);
    }

    public function imageUrl(): ?string
    {
        return PublicImageUrl::for($this->image_path);
    }
}
