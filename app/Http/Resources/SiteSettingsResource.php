<?php

namespace App\Http\Resources;

use App\Models\SiteSetting;
use App\Models\SocialLink;
use App\Models\StoreLocation;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin SiteSetting */
class SiteSettingsResource extends JsonResource
{
    /**
     * @param  iterable<SocialLink>  $socialLinks
     * @param  iterable<StoreLocation>  $storeLocations
     */
    public function __construct(SiteSetting $resource, private iterable $socialLinks, private iterable $storeLocations)
    {
        parent::__construct($resource);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'announcement' => $this->announcement,
            'footerTagline' => $this->footer_tagline,
            'socialLinks' => collect($this->socialLinks)->map(fn ($link) => [
                'label' => $link->label,
                'icon' => $link->icon,
                'href' => $link->href,
            ])->values()->all(),
            'storeLocations' => collect($this->storeLocations)->map(fn ($store) => [
                'name' => $store->name,
                'address' => $store->address,
                'hours' => $store->hours,
            ])->values()->all(),
        ];
    }
}
