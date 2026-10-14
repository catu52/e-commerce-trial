<?php

namespace App\Http\Resources;

use App\Models\HomePage;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin HomePage */
class HomePageResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'heroEyebrow' => $this->hero_eyebrow,
            'heroTitle' => $this->hero_title,
            'heroSubtitle' => $this->hero_subtitle,
            'lookbook' => $this->lookbookItems->map(fn ($item) => [
                'id' => $item->id,
                'title' => $item->title,
                'caption' => $item->caption,
                'link' => $item->link,
                'imageUrl' => $item->imageUrl(),
            ])->values()->all(),
            'editorialTitle' => $this->editorial_title,
            'editorialBody' => $this->editorial_body,
            'editorialImageUrl' => $this->editorialImageUrl(),
        ];
    }
}
