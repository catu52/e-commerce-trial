<?php

namespace Tests\Feature;

use App\Filament\Pages\MediaLibrary;
use App\Models\Article;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Database\Seeders\UserSeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class FilamentMediaLibraryTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
        $this->seed([RolesAndPermissionsSeeder::class, UserSeeder::class]);
        Filament::setCurrentPanel(Filament::getPanel('admin'));
    }

    private function login(string $role): void
    {
        $this->actingAs(User::where('email', "{$role}@system.local")->firstOrFail());
    }

    public function test_admin_and_manager_can_open_and_support_cannot(): void
    {
        $this->login('manager');
        $this->get('/admin/content/media')->assertOk();
    }

    public function test_support_is_forbidden(): void
    {
        $this->login('support');
        $this->get('/admin/content/media')->assertForbidden();
    }

    public function test_lists_files_with_usage_and_filters(): void
    {
        $this->login('admin');
        Storage::disk('public')->put('content/articles/cover.png', 'x');
        Storage::disk('public')->put('media/orphan.png', 'x');
        Article::create(['slug' => 'a', 'title' => 'Used Article', 'excerpt' => 'e', 'body' => 'b', 'status' => 'draft', 'cover_image_path' => 'content/articles/cover.png']);

        Livewire::test(MediaLibrary::class)
            ->assertSee('cover.png')->assertSee('orphan.png')->assertSee('Article: Used Article')
            ->set('usage', 'unused')->assertSee('orphan.png')->assertDontSee('cover.png')
            ->set('usage', 'all')->set('folder', 'media')->assertSee('orphan.png')->assertDontSee('cover.png')
            ->set('folder', '')->set('search', 'cover')->assertSee('cover.png')->assertDontSee('orphan.png');
    }

    public function test_upload_action_stores_files_in_media_folder(): void
    {
        $this->login('admin');
        $file = UploadedFile::fake()->image('hero.png', 50, 50);

        Livewire::test(MediaLibrary::class)
            ->callAction('upload', ['files' => [$file]])
            ->assertHasNoActionErrors();

        $this->assertNotEmpty(Storage::disk('public')->files('media'));
    }

    public function test_delete_unused_but_not_used_or_traversal(): void
    {
        $this->login('admin');
        Storage::disk('public')->put('media/orphan.png', 'x');
        Storage::disk('public')->put('content/cover.png', 'x');
        Article::create(['slug' => 'a', 'title' => 'A', 'excerpt' => 'e', 'body' => 'b', 'status' => 'draft', 'cover_image_path' => 'content/cover.png']);

        $page = Livewire::test(MediaLibrary::class);
        $page->call('deleteFile', 'content/cover.png');
        Storage::disk('public')->assertExists('content/cover.png');
        $page->call('deleteFile', '../.env');
        $page->call('deleteFile', 'media/orphan.png');
        Storage::disk('public')->assertMissing('media/orphan.png');
    }

    public function test_view_mode_toggle_switches_between_grid_compact_and_list(): void
    {
        $this->login('admin');
        Storage::disk('public')->put('media/a.png', 'x');

        Livewire::test(MediaLibrary::class)
            ->assertSet('viewMode', 'grid')->assertSee('600px')->assertDontSee('Modified')
            ->call('setViewMode', 'list')->assertSet('viewMode', 'list')->assertSee('Modified')->assertSee('width:100px')->assertSee('a.png')
            ->call('setViewMode', 'compact')->assertSet('viewMode', 'compact')->assertSee('400px')->assertDontSee('600px')
            ->call('setViewMode', 'bogus')->assertSet('viewMode', 'grid');
    }

    public function test_files_are_paginated_in_every_view(): void
    {
        $this->login('admin');
        foreach (range(1, 30) as $n) {
            Storage::disk('public')->put(sprintf('media/f%02d.png', $n), 'x');
        }

        foreach (['grid', 'compact', 'list'] as $mode) {
            $test = Livewire::test(MediaLibrary::class)->call('setViewMode', $mode)->set('perPage', 12)
                ->assertSee('30 file(s)');
            $this->assertCount(12, $test->viewData('files'));
            $test->call('gotoPage', 3)->assertSet('paginators.page', 3);
            $this->assertCount(6, $test->viewData('files'));
        }
    }
}
