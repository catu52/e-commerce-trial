<?php

namespace App\Filament\Pages;

use App\Filament\Concerns\ManagesContentPage;
use App\Models\HomePage;
use App\Support\PublicImageUrl;
use BackedEnum;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

/**
 * @property-read Schema $form
 */
class ManageHomePage extends Page implements Forms\Contracts\HasForms
{
    use Forms\Concerns\InteractsWithForms;
    use ManagesContentPage;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-home';

    protected static ?string $navigationLabel = 'Home Page';

    protected static ?string $title = 'Home Page';

    protected static ?string $slug = 'content/home-page';

    protected string $view = 'filament.pages.content-form';

    protected static ?int $navigationSort = 1;

    /** @var array<string, mixed> */
    public ?array $data = [];

    public function mount(): void
    {
        $this->form->fill($this->record()->attributesToArray() + ['lookbook' => $this->lookbookState()]);
    }

    private function record(): HomePage
    {
        return HomePage::current();
    }

    /** @return array<int, array<string, mixed>> */
    private function lookbookState(): array
    {
        return $this->record()->lookbookItems()->get()
            ->map(fn ($i) => [
                'id' => $i->id, 'title' => $i->title, 'caption' => $i->caption,
                'link' => $i->link, 'image_path' => $i->image_path,
            ])->all();
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->statePath('data')
            ->components([
                Section::make('Hero')->schema([
                    Forms\Components\TextInput::make('hero_eyebrow')->required()->maxLength(255),
                    Forms\Components\TextInput::make('hero_title')->required()->maxLength(255),
                    Forms\Components\Textarea::make('hero_subtitle')->required()->maxLength(2000),
                ]),
                Section::make('Editorial')->schema([
                    Forms\Components\TextInput::make('editorial_title')->required()->maxLength(255),
                    Forms\Components\Textarea::make('editorial_body')->required()->maxLength(5000)->rows(6),
                    Forms\Components\FileUpload::make('editorial_image_path')
                        ->label('Editorial image')
                        ->image()
                        ->disk(PublicImageUrl::DISK)
                        ->directory('content')
                        ->visibility('public')
                        ->maxSize(4096),
                ]),
                Section::make('Lookbook')->schema([
                    Forms\Components\Repeater::make('lookbook')
                        ->label('Lookbook items')
                        ->reorderable()
                        ->reorderableWithButtons()
                        ->collapsible()
                        ->itemLabel(fn (array $state): ?string => $state['title'] ?? null)
                        ->schema([
                            Forms\Components\Hidden::make('id'),
                            Forms\Components\TextInput::make('title')->required()->maxLength(255),
                            Forms\Components\TextInput::make('caption')->required()->maxLength(255),
                            Forms\Components\TextInput::make('link')
                                ->required()
                                ->maxLength(255)
                                ->helperText('Relative path (/shop, /products/slug) or full URL.')
                                ->rules([fn () => static::linkRule()]),
                            Forms\Components\FileUpload::make('image_path')
                                ->label('Image')
                                ->image()
                                ->disk(PublicImageUrl::DISK)
                                ->directory('content')
                                ->visibility('public')
                                ->maxSize(4096)
                                ->required(),
                        ]),
                ]),
            ]);
    }

    /** Accepts a site-relative path ("/catalog") or an absolute http(s) URL. */
    public static function linkRule(): \Closure
    {
        return function (string $attribute, mixed $value, \Closure $fail): void {
            $value = (string) $value;
            $relative = str_starts_with($value, '/') && ! str_starts_with($value, '//') && ! preg_match('/\s/', $value);
            $absolute = filter_var($value, FILTER_VALIDATE_URL) !== false && preg_match('#^https?://#i', $value);

            if (! $relative && ! $absolute) {
                $fail('The link must be a relative path starting with "/" or a valid http(s) URL.');
            }
        };
    }

    public function save(): void
    {
        abort_unless(static::canAccess(), 403);

        $state = $this->form->getState();
        $page = $this->record();
        $items = $page->lookbookItems()->get();

        $before = $items->pluck('image_path')->push($page->editorial_image_path)->all();

        $page->update([
            'hero_eyebrow' => $state['hero_eyebrow'],
            'hero_title' => $state['hero_title'],
            'hero_subtitle' => $state['hero_subtitle'],
            'editorial_title' => $state['editorial_title'],
            'editorial_body' => $state['editorial_body'],
            'editorial_image_path' => static::firstPath($state['editorial_image_path'] ?? null),
        ]);

        $keptIds = [];
        $after = [static::firstPath($state['editorial_image_path'] ?? null)];
        foreach (array_values($state['lookbook'] ?? []) as $order => $row) {
            $attrs = [
                'title' => $row['title'], 'caption' => $row['caption'], 'link' => $row['link'],
                'image_path' => static::firstPath($row['image_path'] ?? null), 'sort_order' => $order,
            ];
            $item = ! empty($row['id']) ? $items->firstWhere('id', (int) $row['id']) : null;
            $item ? $item->update($attrs) : ($item = $page->lookbookItems()->create($attrs));
            $keptIds[] = $item->id;
            $after[] = $attrs['image_path'];
        }
        $page->lookbookItems()->whereNotIn('id', $keptIds)->delete();

        $this->deleteReplacedFiles($before, $after);
        $this->mount();

        Notification::make()->title('Home page saved')->success()->send();
    }
}
