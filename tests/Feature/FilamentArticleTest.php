<?php

namespace Tests\Feature;

use App\Filament\Resources\ArticleResource\Pages\CreateArticle;
use App\Filament\Resources\ArticleResource\Pages\EditArticle;
use App\Filament\Resources\ArticleResource\Pages\ListArticles;
use App\Models\Article;
use App\Models\User;
use App\Services\ArticlePreviewUrl;
use Database\Seeders\RolesAndPermissionsSeeder;
use Database\Seeders\UserSeeder;
use Filament\Actions\DeleteAction;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class FilamentArticleTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
        $this->seed([RolesAndPermissionsSeeder::class, UserSeeder::class]);
        Filament::setCurrentPanel(Filament::getPanel('admin'));
    }

    private function staff(string $name): User
    {
        return User::where('email', "{$name}@system.local")->firstOrFail();
    }

    private function article(array $o = []): Article
    {
        static $n = 0;
        $n++;

        return Article::create(array_merge([
            'slug' => "a-{$n}", 'title' => "Article {$n}", 'excerpt' => 'Ex', 'body' => 'Body text',
            'status' => 'draft',
        ], $o));
    }

    private function valid(array $o = []): array
    {
        return array_merge([
            'title' => 'On Linen', 'slug' => 'on-linen', 'excerpt' => 'Short', 'body' => '# Hi',
            'status' => 'draft',
        ], $o);
    }

    public function test_admin_can_open_pages(): void
    {
        $this->actingAs($this->staff('admin'))->get('/admin/articles')->assertOk();
        $this->get('/admin/articles/create')->assertOk();
    }

    public function test_manager_can_open_pages(): void
    {
        $this->actingAs($this->staff('manager'))->get('/admin/articles')->assertOk();
        $this->get('/admin/articles/create')->assertOk();
    }

    public function test_support_is_forbidden(): void
    {
        $this->actingAs($this->staff('support'))->get('/admin/articles')->assertForbidden();
    }

    public function test_create_stores_article_with_cover(): void
    {
        $this->actingAs($this->staff('admin'));

        Livewire::test(CreateArticle::class)
            ->fillForm($this->valid(['cover_image_path' => UploadedFile::fake()->image('c.png'), 'category' => 'Essay']))
            ->call('create')
            ->assertHasNoFormErrors();

        $a = Article::where('slug', 'on-linen')->firstOrFail();
        $this->assertSame('Essay', $a->category);
        $this->assertStringStartsWith('content/articles/', $a->cover_image_path);
        Storage::disk('public')->assertExists($a->cover_image_path);
    }

    public function test_validation(): void
    {
        $this->actingAs($this->staff('admin'));
        $this->article(['slug' => 'taken']);

        Livewire::test(CreateArticle::class)
            ->fillForm(['title' => '', 'slug' => '', 'excerpt' => '', 'body' => ''])
            ->call('create')
            ->assertHasFormErrors(['title' => 'required', 'slug' => 'required', 'excerpt' => 'required', 'body' => 'required']);

        Livewire::test(CreateArticle::class)
            ->fillForm($this->valid(['slug' => 'taken', 'excerpt' => str_repeat('x', 301)]))
            ->call('create')
            ->assertHasFormErrors(['slug' => 'unique', 'excerpt' => 'max']);
    }

    public function test_publishing_without_date_sets_published_at(): void
    {
        $this->actingAs($this->staff('admin'));

        Livewire::test(CreateArticle::class)
            ->fillForm($this->valid(['status' => 'published']))
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertNotNull(Article::where('slug', 'on-linen')->first()->published_at);
    }

    public function test_edit_replaces_cover_and_deletes_old_file(): void
    {
        $this->actingAs($this->staff('admin'));
        Storage::disk('public')->put('content/articles/old.png', 'x');
        $a = $this->article(['cover_image_path' => 'content/articles/old.png']);

        Livewire::test(EditArticle::class, ['record' => $a->getKey()])
            ->fillForm(['cover_image_path' => [UploadedFile::fake()->image('n.png')]])
            ->call('save')
            ->assertHasNoFormErrors();

        $a->refresh();
        $this->assertNotSame('content/articles/old.png', $a->cover_image_path);
        Storage::disk('public')->assertMissing('content/articles/old.png');
        Storage::disk('public')->assertExists($a->cover_image_path);
    }

    public function test_delete_removes_cover_file(): void
    {
        $this->actingAs($this->staff('admin'));
        Storage::disk('public')->put('content/articles/x.png', 'x');
        $a = $this->article(['cover_image_path' => 'content/articles/x.png']);

        Livewire::test(EditArticle::class, ['record' => $a->getKey()])
            ->callAction(DeleteAction::class);

        $this->assertModelMissing($a);
        Storage::disk('public')->assertMissing('content/articles/x.png');
    }

    public function test_filters(): void
    {
        $this->actingAs($this->staff('admin'));
        $pub = $this->article(['status' => 'published', 'published_at' => now()->subDay(), 'category' => 'Essay', 'is_featured' => true]);
        $draft = $this->article(['category' => 'Materials']);

        Livewire::test(ListArticles::class)
            ->filterTable('status', 'published')->assertCanSeeTableRecords([$pub])->assertCanNotSeeTableRecords([$draft])
            ->removeTableFilters()
            ->filterTable('category', 'Materials')->assertCanSeeTableRecords([$draft])->assertCanNotSeeTableRecords([$pub])
            ->removeTableFilters()
            ->filterTable('is_featured', true)->assertCanSeeTableRecords([$pub])->assertCanNotSeeTableRecords([$draft]);
    }

    public function test_view_on_site_only_when_published(): void
    {
        $this->actingAs($this->staff('admin'));
        $pub = $this->article(['status' => 'published', 'published_at' => now()->subDay(), 'slug' => 'live']);
        $draft = $this->article();

        Livewire::test(ListArticles::class)
            ->assertTableActionVisible('viewOnSite', $pub)
            ->assertTableActionVisible('viewOnSite', $draft)
            ->assertTableActionHasUrl('viewOnSite', config('shop.frontend_url').'/editorial/live', $pub)
            ->assertTableActionHasLabel('viewOnSite', 'View on site', $pub)
            ->assertTableActionHasLabel('viewOnSite', 'Preview on site', $draft);

        $url = app(ArticlePreviewUrl::class)->for($draft);
        $this->assertStringStartsWith(config('shop.frontend_url').'/editorial/'.$draft->slug.'?preview=1&expires=', $url);
        $this->assertStringContainsString('signature=', $url);
    }
}
