<?php

namespace Tests\Feature;

use App\Models\Article;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ArticleApiTest extends TestCase
{
    use RefreshDatabase;

    private function article(string $slug, array $attrs = []): Article
    {
        return Article::create($attrs + [
            'slug' => $slug,
            'title' => 'Title '.$slug,
            'excerpt' => 'Excerpt',
            'body' => str_repeat('word ', 450),
            'category' => 'Essay',
            'status' => Article::STATUS_PUBLISHED,
            'published_at' => now()->subDay(),
        ]);
    }

    public function test_lists_only_published_newest_first_without_body(): void
    {
        $this->article('old', ['published_at' => now()->subDays(5)]);
        $this->article('new', ['published_at' => now()->subDay()]);
        $this->article('draft', ['status' => Article::STATUS_DRAFT, 'published_at' => null]);
        $this->article('future', ['published_at' => now()->addDay()]);

        $res = $this->getJson('/api/v1/content/articles')->assertOk();

        $this->assertSame(['new', 'old'], collect($res->json('data'))->pluck('slug')->all());
        $this->assertArrayNotHasKey('body', $res->json('data.0'));
        $res->assertJsonPath('meta.total', 2)->assertJsonPath('meta.per_page', 9);
        $this->assertGreaterThanOrEqual(1, $res->json('data.0.readingMinutes'));
        $this->assertSame('KRAFT Studio', $res->json('data.0.author'));
        $this->assertSame('Title new', $res->json('data.0.coverImageAlt'));
    }

    public function test_pagination_and_per_page_cap(): void
    {
        foreach (range(1, 30) as $i) {
            $this->article("a$i", ['published_at' => now()->subMinutes($i)]);
        }

        $this->getJson('/api/v1/content/articles?per_page=5&page=2')
            ->assertJsonPath('meta.current_page', 2)
            ->assertJsonPath('meta.last_page', 6)
            ->assertJsonCount(5, 'data');

        $this->getJson('/api/v1/content/articles?per_page=500')
            ->assertJsonPath('meta.per_page', 24)
            ->assertJsonCount(24, 'data');
    }

    public function test_category_filter_and_categories_list(): void
    {
        $this->article('e1', ['category' => 'Essay']);
        $this->article('m1', ['category' => 'Materials']);
        $this->article('n1', ['category' => null]);
        $this->article('hidden', ['category' => 'Zine', 'status' => Article::STATUS_DRAFT]);

        $res = $this->getJson('/api/v1/content/articles?category=Materials')->assertOk();
        $this->assertSame(['m1'], collect($res->json('data'))->pluck('slug')->all());
        $this->assertSame(['Essay', 'Materials'], $res->json('categories'));
    }

    public function test_detail_includes_body_and_cover_url(): void
    {
        $this->article('story', ['cover_image_path' => 'articles/x.jpg', 'cover_image_alt' => 'Alt', 'author_name' => 'Ana']);

        $res = $this->getJson('/api/v1/content/articles/story')->assertOk();
        $res->assertJsonPath('data.slug', 'story')->assertJsonPath('data.coverImageAlt', 'Alt')->assertJsonPath('data.author', 'Ana');
        $this->assertNotEmpty($res->json('data.body'));
        $this->assertStringStartsWith('http', $res->json('data.coverImage'));
        $this->assertGreaterThanOrEqual(1, $res->json('data.readingMinutes'));
    }

    public function test_cover_image_null_when_missing(): void
    {
        $this->article('plain');

        $this->getJson('/api/v1/content/articles/plain')->assertJsonPath('data.coverImage', null);
    }

    public function test_unpublished_and_unknown_return_404(): void
    {
        $this->article('draft', ['status' => Article::STATUS_DRAFT, 'published_at' => null]);
        $this->article('future', ['published_at' => now()->addDay()]);

        foreach (['draft', 'future', 'nope'] as $slug) {
            $this->getJson("/api/v1/content/articles/$slug")->assertNotFound()->assertJsonStructure(['message']);
        }
    }

    public function test_related_prefers_same_category_then_latest_and_excludes_self(): void
    {
        $this->article('main', ['category' => 'Essay', 'published_at' => now()->subDays(1)]);
        $this->article('same-old', ['category' => 'Essay', 'published_at' => now()->subDays(20)]);
        $this->article('other-new', ['category' => 'Materials', 'published_at' => now()->subHours(2)]);
        $this->article('same-mid', ['category' => 'Essay', 'published_at' => now()->subDays(10)]);
        $this->article('other-old', ['category' => 'Materials', 'published_at' => now()->subDays(30)]);
        $this->article('draft', ['category' => 'Essay', 'status' => Article::STATUS_DRAFT, 'published_at' => null]);

        $res = $this->getJson('/api/v1/content/articles/main')->assertOk();
        $related = collect($res->json('related'));

        $this->assertSame(['same-mid', 'same-old', 'other-new'], $related->pluck('slug')->all());
        $this->assertArrayNotHasKey('body', $res->json('related.0'));
    }
}
