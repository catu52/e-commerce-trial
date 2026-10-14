<?php

namespace Tests\Feature;

use App\Models\Article;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SeoTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['shop.frontend_url' => 'https://shop.example.com']);
    }

    private function product(string $slug, string $sku): Product
    {
        return Product::create(['sku' => $sku, 'name' => 'P '.$sku, 'slug' => $slug, 'base_price' => 10, 'stock_quantity' => 1]);
    }

    public function test_sitemap_lists_static_and_product_urls(): void
    {
        $p = $this->product('parka', 'S1');
        $this->product('wool-coat', 'S2');

        $response = $this->get('/sitemap.xml')->assertOk();
        $this->assertStringContainsString('application/xml', $response->headers->get('Content-Type'));
        $this->assertStringContainsString('public', $response->headers->get('Cache-Control'));
        $this->assertStringContainsString('max-age=3600', $response->headers->get('Cache-Control'));

        $xml = simplexml_load_string($response->getContent());
        $this->assertNotFalse($xml);
        $locs = [];
        foreach ($xml->url as $url) {
            $locs[] = (string) $url->loc;
        }

        foreach (['/', '/shop', '/shop/men', '/shop/women', '/flash-sale', '/editorial', '/products/parka', '/products/wool-coat'] as $path) {
            $this->assertContains('https://shop.example.com'.$path, $locs);
        }
        $this->assertCount(8, $locs);

        $lastmod = (string) $xml->url[6]->lastmod;
        $this->assertSame($p->updated_at->toAtomString(), $lastmod);
    }

    public function test_sitemap_lists_only_published_articles(): void
    {
        $make = fn (string $slug, string $status, $at) => Article::create([
            'slug' => $slug, 'title' => $slug, 'excerpt' => 'e', 'body' => 'x', 'status' => $status, 'published_at' => $at,
        ]);
        $make('live-one', Article::STATUS_PUBLISHED, now()->subDay());
        $make('a-draft', Article::STATUS_DRAFT, null);
        $make('coming-soon', Article::STATUS_PUBLISHED, now()->addDay());

        $content = $this->get('/sitemap.xml')->getContent();
        $this->assertStringContainsString('https://shop.example.com/editorial/live-one', $content);
        $this->assertStringNotContainsString('a-draft', $content);
        $this->assertStringNotContainsString('coming-soon', $content);
    }

    public function test_sitemap_excludes_checkout(): void
    {
        $this->product('parka', 'S1');
        $this->assertStringNotContainsString('checkout', $this->get('/sitemap.xml')->getContent());
    }

    public function test_sitemap_escapes_special_characters(): void
    {
        $this->product('a&b<c>', 'S1');

        $content = $this->get('/sitemap.xml')->getContent();
        $xml = simplexml_load_string($content);
        $this->assertNotFalse($xml);
        $this->assertStringNotContainsString('<c>', $content);
        $this->assertSame('https://shop.example.com/products/a%26b%3Cc%3E', (string) $xml->url[6]->loc);
    }

    public function test_robots_txt(): void
    {
        $response = $this->get('/robots.txt')->assertOk();
        $this->assertStringContainsString('text/plain', $response->headers->get('Content-Type'));

        $body = $response->getContent();
        $this->assertStringContainsString("User-agent: *\n", $body);
        $this->assertStringContainsString("Allow: /\n", $body);
        $this->assertStringContainsString("Disallow: /checkout/\n", $body);
        $this->assertStringContainsString('Sitemap: '.url('/sitemap.xml'), $body);
    }
}
