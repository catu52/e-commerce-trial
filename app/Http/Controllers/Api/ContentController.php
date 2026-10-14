<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\HomePageResource;
use App\Http\Resources\SiteSettingsResource;
use App\Models\HomePage;
use App\Models\SiteSetting;
use App\Models\SocialLink;
use App\Models\StoreLocation;
use Illuminate\Http\JsonResponse;

class ContentController extends Controller
{
    public function home(): JsonResponse
    {
        $page = HomePage::current()->load('lookbookItems');

        return response()->json(['data' => (new HomePageResource($page))->resolve()]);
    }

    public function siteSettings(): JsonResponse
    {
        $resource = new SiteSettingsResource(
            SiteSetting::current(),
            SocialLink::activeOrdered()->get(),
            StoreLocation::activeOrdered()->get(),
        );

        return response()->json(['data' => $resource->resolve()]);
    }
}
