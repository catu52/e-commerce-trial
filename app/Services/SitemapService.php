<?php

namespace App\Services;

use App\Models\Article;
use App\Models\Product;

class SitemapService
{
    private const STATIC_PATHS = ['/', '/shop', '/shop/men', '/shop/women', '/flash-sale', '/editorial'];

    /** @return list<array{loc: string, lastmod: ?string}> */
    public function entries(): array
    {
        $base = rtrim((string) config('shop.frontend_url'), '/');

        $entries = array_map(fn (string $path) => [
            'loc' => $base.($path === '/' ? '/' : $path),
            'lastmod' => null,
        ], self::STATIC_PATHS);

        foreach (Product::query()->orderBy('id')->get(['slug', 'updated_at']) as $product) {
            $entries[] = [
                'loc' => $base.'/products/'.rawurlencode($product->slug),
                'lastmod' => $product->updated_at?->toAtomString(),
            ];
        }

        foreach (Article::published()->orderBy('id')->get(['slug', 'updated_at']) as $article) {
            $entries[] = [
                'loc' => $base.'/editorial/'.rawurlencode($article->slug),
                'lastmod' => $article->updated_at?->toAtomString(),
            ];
        }

        return $entries;
    }

    public function sitemapXml(): string
    {
        $xml = '<?xml version="1.0" encoding="UTF-8"?>'."\n";
        $xml .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">'."\n";

        foreach ($this->entries() as $entry) {
            $xml .= '  <url><loc>'.$this->escape($entry['loc']).'</loc>';
            if ($entry['lastmod']) {
                $xml .= '<lastmod>'.$this->escape($entry['lastmod']).'</lastmod>';
            }
            $xml .= "</url>\n";
        }

        return $xml.'</urlset>'."\n";
    }

    public function robotsTxt(string $sitemapUrl): string
    {
        return implode("\n", [
            'User-agent: *',
            'Allow: /',
            'Disallow: /checkout/',
            'Sitemap: '.$sitemapUrl,
        ])."\n";
    }

    private function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_XML1 | ENT_QUOTES, 'UTF-8');
    }
}
