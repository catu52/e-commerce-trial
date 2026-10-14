<?php

namespace App\Http\Controllers;

use App\Services\SitemapService;
use Illuminate\Http\Response;

class SeoController extends Controller
{
    public function __construct(private readonly SitemapService $sitemap) {}

    public function sitemap(): Response
    {
        return response($this->sitemap->sitemapXml(), 200, [
            'Content-Type' => 'application/xml; charset=UTF-8',
            'Cache-Control' => 'public, max-age=3600',
        ]);
    }

    public function robots(): Response
    {
        return response($this->sitemap->robotsTxt(url('/sitemap.xml')), 200, [
            'Content-Type' => 'text/plain; charset=UTF-8',
            'Cache-Control' => 'public, max-age=3600',
        ]);
    }
}
