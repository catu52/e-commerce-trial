<?php

namespace Tests\Feature;

use App\Filament\Pages\ManageHomePage;
use App\Filament\Pages\ManageSiteSettings;
use App\Models\Client;
use App\Models\HomePage;
use App\Models\SocialLink;
use App\Models\StoreLocation;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Database\Seeders\UserSeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class FilamentContentTest extends TestCase
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

    private function validHome(array $overrides = []): array
    {
        return array_merge([
            'hero_eyebrow' => 'NEW', 'hero_title' => 'Hero', 'hero_subtitle' => 'Sub',
            'editorial_title' => 'Ed', 'editorial_body' => 'Body', 'lookbook' => [],
        ], $overrides);
    }

    /** @return array<string, array<int, string>> */
    public static function contentRoles(): array
    {
        return ['admin' => ['admin'], 'manager' => ['manager']];
    }

    #[DataProvider('contentRoles')]
    public function test_pages_render_for_authorized_staff(string $role): void
    {
        $this->actingAs($this->staff($role))->get('/admin/content/home-page')->assertOk()->assertSee('Home Page');
        Livewire::test(ManageSiteSettings::class)->assertSuccessful();
        Livewire::test(ManageHomePage::class)->assertSuccessful();
    }

    public function test_site_settings_page_renders_over_http(): void
    {
        $this->actingAs($this->staff('admin'))->get('/admin/content/site-settings')->assertOk();
    }

    public function test_support_staff_without_content_manage_is_denied(): void
    {
        $this->actingAs($this->staff('support'))->get('/admin/content/home-page')->assertForbidden();
    }

    public function test_user_without_any_permission_is_denied(): void
    {
        $this->actingAs(User::factory()->create())->get('/admin/content/site-settings')->assertForbidden();
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get('/admin/content/home-page')->assertRedirect('/admin/login');
    }

    public function test_client_is_denied(): void
    {
        $client = Client::create(['first_name' => 'A', 'last_name' => 'B', 'email' => 'x@example.com', 'password' => 'secret123']);
        $this->actingAs($client)->get('/admin/content/site-settings')->assertForbidden();
    }

    public function test_saving_home_page_updates_db_and_public_api_immediately(): void
    {
        $this->getJson('/api/v1/content/home')->assertJsonPath('data.heroTitle', '');

        Livewire::actingAs($this->staff('admin'))->test(ManageHomePage::class)
            ->fillForm($this->validHome(['hero_title' => 'Summer Drop']))
            ->call('save')
            ->assertHasNoFormErrors()
            ->assertNotified();

        $this->assertDatabaseHas('home_pages', ['hero_title' => 'Summer Drop']);
        $this->getJson('/api/v1/content/home')->assertJsonPath('data.heroTitle', 'Summer Drop');

        Livewire::actingAs($this->staff('admin'))->test(ManageHomePage::class)
            ->fillForm($this->validHome(['hero_title' => 'Winter']))
            ->call('save');
        $this->getJson('/api/v1/content/home')->assertJsonPath('data.heroTitle', 'Winter');
    }

    public function test_lookbook_items_are_created_reordered_and_removed(): void
    {
        $page = HomePage::current();
        $a = $page->lookbookItems()->create(['title' => 'A', 'caption' => 'c', 'link' => '/a', 'sort_order' => 0, 'image_path' => 'content/a.jpg']);
        $b = $page->lookbookItems()->create(['title' => 'B', 'caption' => 'c', 'link' => '/b', 'sort_order' => 1, 'image_path' => 'content/b.jpg']);

        Storage::disk('public')->put('content/a.jpg', 'a');
        Storage::disk('public')->put('content/b.jpg', 'b');
        Storage::disk('public')->put('content/c.jpg', 'c');

        $component = Livewire::actingAs($this->staff('admin'))->test(ManageHomePage::class)->fillForm(Arr::except($this->validHome(), 'lookbook'));
        $rows = array_values($component->get('data.lookbook'));
        $this->assertSame(['A', 'B'], array_column($rows, 'title'));

        $component->set('data.lookbook', [
            'x1' => $rows[1],
            'x2' => $rows[0],
            'x3' => ['id' => null, 'title' => 'C', 'caption' => 'cc', 'link' => 'https://example.com/c', 'image_path' => ['u1' => 'content/c.jpg']],
        ])->call('save')->assertHasNoFormErrors();

        $titles = collect($this->getJson('/api/v1/content/home')->json('data.lookbook'))->pluck('title');
        $this->assertSame(['B', 'A', 'C'], $titles->all());

        $component->set('data.lookbook', [array_values($component->get('data.lookbook'))[0]])->call('save');
        $this->assertSame([$b->id], HomePage::current()->lookbookItems()->pluck('id')->all());
        $this->assertDatabaseMissing('lookbook_items', ['id' => $a->id]);
    }

    public function test_home_page_validation(): void
    {
        Livewire::actingAs($this->staff('admin'))->test(ManageHomePage::class)
            ->fillForm($this->validHome([
                'hero_title' => '', 'hero_eyebrow' => str_repeat('x', 256),
                'lookbook' => [['id' => null, 'title' => 'T', 'caption' => 'c', 'link' => 'javascript:alert(1)', 'image_path' => null]],
            ]))
            ->call('save')
            ->assertHasFormErrors([
                'hero_title' => 'required',
                'hero_eyebrow' => 'max',
                'lookbook.0.link',
                'lookbook.0.image_path' => 'required',
            ]);
    }

    public function test_lookbook_link_accepts_relative_and_absolute_but_not_protocol_relative(): void
    {
        $rule = ManageHomePage::linkRule();
        $check = function (string $v) use ($rule): bool {
            $failed = false;
            $rule('link', $v, function () use (&$failed) {
                $failed = true;
            });

            return ! $failed;
        };

        $this->assertTrue($check('/catalog'));
        $this->assertTrue($check('https://example.com/x'));
        $this->assertFalse($check('//evil.com'));
        $this->assertFalse($check('catalog'));
        $this->assertFalse($check('javascript:alert(1)'));
    }

    public function test_editorial_image_is_stored_returned_and_replaced_file_deleted(): void
    {
        $component = Livewire::actingAs($this->staff('admin'))->test(ManageHomePage::class)
            ->fillForm($this->validHome(['editorial_image_path' => UploadedFile::fake()->image('one.png')]))
            ->call('save')
            ->assertHasNoFormErrors();

        $first = HomePage::current()->editorial_image_path;
        $this->assertStringStartsWith('content/', $first);
        Storage::disk('public')->assertExists($first);
        $this->assertSame(Storage::disk('public')->url($first), $this->getJson('/api/v1/content/home')->json('data.editorialImageUrl'));

        $component->set('data.editorial_image_path', [])
            ->fillForm(['editorial_image_path' => UploadedFile::fake()->image('two.png')])->call('save')
            ->assertHasNoFormErrors();

        $second = HomePage::current()->editorial_image_path;
        $this->assertNotSame($first, $second);
        Storage::disk('public')->assertMissing($first);
        Storage::disk('public')->assertExists($second);
    }

    public function test_lookbook_image_upload_is_returned_as_image_url(): void
    {
        Livewire::actingAs($this->staff('admin'))->test(ManageHomePage::class)
            ->fillForm($this->validHome(['lookbook' => [[
                'id' => null, 'title' => 'T', 'caption' => 'c', 'link' => '/t',
                'image_path' => [UploadedFile::fake()->image('look.png')],
            ]]]))
            ->call('save')
            ->assertHasNoFormErrors();

        $path = HomePage::current()->lookbookItems()->first()->image_path;
        Storage::disk('public')->assertExists($path);
        $this->assertSame(Storage::disk('public')->url($path), $this->getJson('/api/v1/content/home')->json('data.lookbook.0.imageUrl'));
    }

    public function test_image_type_and_size_are_validated(): void
    {
        Livewire::actingAs($this->staff('admin'))->test(ManageHomePage::class)
            ->fillForm($this->validHome(['editorial_image_path' => UploadedFile::fake()->create('doc.pdf', 10, 'application/pdf')]))
            ->call('save')
            ->assertHasFormErrors(['editorial_image_path']);

        Livewire::actingAs($this->staff('admin'))->test(ManageHomePage::class)
            ->fillForm($this->validHome(['editorial_image_path' => UploadedFile::fake()->image('big.png')->size(5000)]))
            ->call('save')
            ->assertHasFormErrors(['editorial_image_path']);
    }

    public function test_site_settings_save_reorder_toggle_and_delete_reflect_in_api(): void
    {
        $component = Livewire::actingAs($this->staff('manager'))->test(ManageSiteSettings::class)
            ->fillForm([
                'announcement' => 'Free shipping', 'footer_tagline' => 'Made well',
                'social_links' => [
                    ['id' => null, 'label' => 'IG', 'icon' => 'instagram', 'href' => 'https://instagram.com/x', 'is_active' => true],
                    ['id' => null, 'label' => 'TW', 'icon' => 'twitter', 'href' => 'https://twitter.com/x', 'is_active' => true],
                ],
                'store_locations' => [
                    ['id' => null, 'name' => 'Downtown', 'address' => '1 Main', 'hours' => '9-5', 'is_active' => true],
                    ['id' => null, 'name' => 'Mall', 'address' => '2 Side', 'hours' => '10-8', 'is_active' => true],
                ],
            ])
            ->call('save')
            ->assertHasNoFormErrors()
            ->assertNotified();

        $api = $this->getJson('/api/v1/content/site-settings')->assertOk();
        $api->assertJsonPath('data.announcement', 'Free shipping')->assertJsonPath('data.footerTagline', 'Made well');
        $this->assertSame(['IG', 'TW'], collect($api->json('data.socialLinks'))->pluck('label')->all());
        $this->assertSame(['Downtown', 'Mall'], collect($api->json('data.storeLocations'))->pluck('name')->all());

        $links = array_values($component->get('data.social_links'));
        $locs = array_values($component->get('data.store_locations'));
        $links[0]['is_active'] = false;
        $locs = [$locs[1], $locs[0]];
        $locs[0]['is_active'] = true;
        $locs[1]['is_active'] = false;
        $component->set('data.social_links', $links)->set('data.store_locations', $locs)->call('save');

        $api = $this->getJson('/api/v1/content/site-settings');
        $this->assertSame(['TW'], collect($api->json('data.socialLinks'))->pluck('label')->all());
        $this->assertSame(['Mall'], collect($api->json('data.storeLocations'))->pluck('name')->all());

        $locs[0]['is_active'] = true;
        $locs[1]['is_active'] = true;
        $component->set('data.store_locations', $locs)->call('save');
        $this->assertSame(['Mall', 'Downtown'], collect($this->getJson('/api/v1/content/site-settings')->json('data.storeLocations'))->pluck('name')->all());

        $component->set('data.social_links', [])->call('save');
        $this->assertSame(0, SocialLink::count());
        $this->assertSame(2, StoreLocation::count());
    }

    public function test_site_settings_validation(): void
    {
        Livewire::actingAs($this->staff('admin'))->test(ManageSiteSettings::class)
            ->fillForm([
                'footer_tagline' => '',
                'social_links' => [['id' => null, 'label' => '', 'icon' => '', 'href' => 'not a url', 'is_active' => true]],
                'store_locations' => [['id' => null, 'name' => '', 'address' => '', 'hours' => '', 'is_active' => true]],
            ])
            ->call('save')
            ->assertHasFormErrors([
                'footer_tagline' => 'required',
                'social_links.0.label' => 'required',
                'social_links.0.href' => 'url',
                'store_locations.0.name' => 'required',
            ]);
    }
}
