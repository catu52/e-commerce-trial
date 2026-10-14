<?php

namespace Tests\Feature;

use App\Models\Article;
use App\Services\ArticlePreviewUrl;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class ArticlePreviewTest extends TestCase
{
    use RefreshDatabase;

    private function article(string $slug, array $attrs = []): Article
    {
        return Article::create($attrs + ['slug' => $slug, 'title' => ucfirst($slug), 'excerpt' => 'e', 'body' => 'Body text', 'status' => 'draft']);
    }

    private function signed(string $slug, $expires = null): string
    {
        return URL::temporarySignedRoute('articles.preview', $expires ?? now()->addHour(), ['slug' => $slug], absolute: false);
    }

    public function test_draft_is_previewable_with_a_valid_signature_and_not_publicly(): void
    {
        $this->article('wip');

        $this->getJson('/api/v1/content/articles/wip')->assertNotFound();

        $this->getJson($this->signed('wip'))
            ->assertOk()
            ->assertJsonPath('data.slug', 'wip')
            ->assertJsonPath('data.body', 'Body text')
            ->assertJsonPath('preview.status', 'draft')
            ->assertHeader('X-Robots-Tag', 'noindex, nofollow')
            ->assertHeader('Cache-Control', 'no-store, private');
    }

    public function test_scheduled_article_reports_scheduled_status(): void
    {
        $this->article('soon', ['status' => 'published', 'published_at' => now()->addDay()]);

        $this->getJson($this->signed('soon'))->assertOk()->assertJsonPath('preview.status', 'scheduled');
    }

    public function test_unsigned_tampered_or_expired_links_are_rejected(): void
    {
        $this->article('wip');
        $this->article('other');

        $this->getJson('/api/v1/content/articles/wip/preview')->assertForbidden();

        $tampered = str_replace('/wip/', '/other/', $this->signed('wip'));
        $this->getJson($tampered)->assertForbidden();

        $this->getJson($this->signed('wip', now()->subMinute()))->assertForbidden();
    }

    public function test_unknown_slug_with_valid_signature_is_404(): void
    {
        $this->getJson($this->signed('ghost'))->assertNotFound();
    }

    public function test_preview_url_for_live_article_is_the_public_one_and_for_unpublished_is_signed(): void
    {
        $live = $this->article('live', ['status' => 'published', 'published_at' => now()->subDay()]);
        $draft = $this->article('wip');
        $service = app(ArticlePreviewUrl::class);

        $this->assertSame(config('shop.frontend_url').'/editorial/live', $service->for($live));

        parse_str(parse_url($service->for($draft), PHP_URL_QUERY), $q);
        $path = '/api/v1/content/articles/wip/preview?expires='.$q['expires'].'&signature='.$q['signature'];
        $this->getJson($path)->assertOk()->assertJsonPath('data.slug', 'wip');
    }
}
