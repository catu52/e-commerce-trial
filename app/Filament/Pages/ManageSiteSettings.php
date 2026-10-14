<?php

namespace App\Filament\Pages;

use App\Filament\Concerns\ManagesContentPage;
use App\Models\SiteSetting;
use App\Models\SocialLink;
use App\Models\StoreLocation;
use BackedEnum;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Model;

/**
 * @property-read Schema $form
 */
class ManageSiteSettings extends Page implements Forms\Contracts\HasForms
{
    use Forms\Concerns\InteractsWithForms;
    use ManagesContentPage;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-cog-6-tooth';

    protected static ?string $navigationLabel = 'Site Settings';

    protected static ?string $title = 'Site Settings';

    protected static ?string $slug = 'content/site-settings';

    protected string $view = 'filament.pages.content-form';

    protected static ?int $navigationSort = 2;

    /** @var array<string, mixed> */
    public ?array $data = [];

    public function mount(): void
    {
        $this->form->fill(SiteSetting::current()->attributesToArray() + [
            'social_links' => $this->rows(SocialLink::class, ['label', 'icon', 'href', 'is_active']),
            'store_locations' => $this->rows(StoreLocation::class, ['name', 'address', 'hours', 'is_active']),
        ]);
    }

    /**
     * @param  class-string<Model>  $model
     * @param  array<int, string>  $columns
     * @return array<int, array<string, mixed>>
     */
    private function rows(string $model, array $columns): array
    {
        return $model::query()->orderBy('sort_order')->orderBy('id')->get()
            ->map(fn ($r) => ['id' => $r->id] + $r->only($columns))->all();
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->statePath('data')
            ->components([
                Section::make('Storefront copy')->schema([
                    Forms\Components\Textarea::make('announcement')->maxLength(1000)->helperText('Leave empty to hide the announcement bar.'),
                    Forms\Components\Textarea::make('footer_tagline')->required()->maxLength(1000),
                ]),
                Section::make('Social links')->schema([
                    Forms\Components\Repeater::make('social_links')
                        ->label('Links')
                        ->reorderableWithButtons()
                        ->collapsible()
                        ->itemLabel(fn (array $state): ?string => $state['label'] ?? null)
                        ->schema([
                            Forms\Components\Hidden::make('id'),
                            Forms\Components\TextInput::make('label')->required()->maxLength(255),
                            Forms\Components\TextInput::make('icon')->maxLength(255)->helperText('Icon key used by the storefront, e.g. instagram.'),
                            Forms\Components\TextInput::make('href')->label('URL')->required()->url()->maxLength(255),
                            Forms\Components\Toggle::make('is_active')->label('Active')->default(true),
                        ]),
                ]),
                Section::make('Store locations')->schema([
                    Forms\Components\Repeater::make('store_locations')
                        ->label('Locations')
                        ->reorderableWithButtons()
                        ->collapsible()
                        ->itemLabel(fn (array $state): ?string => $state['name'] ?? null)
                        ->schema([
                            Forms\Components\Hidden::make('id'),
                            Forms\Components\TextInput::make('name')->required()->maxLength(255),
                            Forms\Components\TextInput::make('address')->required()->maxLength(255),
                            Forms\Components\TextInput::make('hours')->required()->maxLength(255),
                            Forms\Components\Toggle::make('is_active')->label('Active')->default(true),
                        ]),
                ]),
            ]);
    }

    public function save(): void
    {
        abort_unless(static::canAccess(), 403);

        $state = $this->form->getState();

        SiteSetting::current()->update([
            'announcement' => (string) ($state['announcement'] ?? ''),
            'footer_tagline' => $state['footer_tagline'],
        ]);

        $this->syncRows(SocialLink::class, $state['social_links'] ?? [], ['label', 'icon', 'href', 'is_active']);
        $this->syncRows(StoreLocation::class, $state['store_locations'] ?? [], ['name', 'address', 'hours', 'is_active']);

        $this->mount();

        Notification::make()->title('Site settings saved')->success()->send();
    }

    /**
     * Upsert rows in submitted order (sort_order = position) and delete the ones removed from the form.
     *
     * @param  class-string<Model>  $model
     * @param  array<int, array<string, mixed>>  $rows
     * @param  array<int, string>  $columns
     */
    private function syncRows(string $model, array $rows, array $columns): void
    {
        $existing = $model::query()->get()->keyBy('id');
        $kept = [];

        foreach (array_values($rows) as $order => $row) {
            $attrs = array_intersect_key($row, array_flip($columns)) + ['sort_order' => $order];
            $attrs['sort_order'] = $order;
            foreach (['icon', 'address', 'hours'] as $nullable) {
                if (in_array($nullable, $columns, true)) {
                    $attrs[$nullable] = (string) ($attrs[$nullable] ?? '');
                }
            }
            $attrs['is_active'] = (bool) ($attrs['is_active'] ?? false);

            $record = ! empty($row['id']) ? $existing->get((int) $row['id']) : null;
            $record ? $record->update($attrs) : ($record = $model::create($attrs));
            $kept[] = $record->id;
        }

        $model::query()->whereNotIn('id', $kept)->delete();
    }
}
