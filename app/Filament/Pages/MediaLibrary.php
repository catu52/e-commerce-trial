<?php

namespace App\Filament\Pages;

use App\Filament\Concerns\ManagesContentPage;
use App\Services\MediaLibraryService;
use App\Support\PublicImageUrl;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Pagination\LengthAwarePaginator;
use InvalidArgumentException;
use Livewire\Attributes\Url;
use Livewire\WithPagination;

class MediaLibrary extends Page
{
    use ManagesContentPage;
    use WithPagination;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-photo';

    protected static ?string $navigationLabel = 'Media';

    protected static ?string $title = 'Media library';

    protected static ?string $slug = 'content/media';

    protected static ?int $navigationSort = 4;

    protected string $view = 'filament.pages.media-library';

    #[Url]
    public string $search = '';

    #[Url]
    public string $folder = '';

    #[Url]
    public string $usage = 'all';

    #[Url]
    public int $perPage = 24;

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedFolder(): void
    {
        $this->resetPage();
    }

    public function updatedUsage(): void
    {
        $this->resetPage();
    }

    public function updatedPerPage(): void
    {
        $this->perPage = in_array($this->perPage, [12, 24, 48, 96], true) ? $this->perPage : 24;
        $this->resetPage();
    }

    public const VIEW_MODES = ['grid', 'compact', 'list'];

    #[Url]
    public string $viewMode = 'grid';

    public function setViewMode(string $viewMode): void
    {
        $this->viewMode = in_array($viewMode, self::VIEW_MODES, true) ? $viewMode : 'grid';
        $this->resetPage();
    }

    public function mount(): void
    {
        abort_unless(static::canAccess(), 403);
    }

    private function media(): MediaLibraryService
    {
        return app(MediaLibraryService::class);
    }

    /** @return array<string, mixed> */
    protected function getViewData(): array
    {
        $all = $this->media()->list($this->folder ?: null, $this->search, $this->usage);
        $perPage = in_array($this->perPage, [12, 24, 48, 96], true) ? $this->perPage : 24;
        $page = min(max($this->getPage(), 1), max((int) ceil($all->count() / $perPage), 1));
        $paginator = new LengthAwarePaginator($all->forPage($page, $perPage)->values(), $all->count(), $perPage, $page, ['path' => request()->url()]);

        return [
            'files' => $paginator->getCollection(),
            'paginator' => $paginator,
            'total' => $all->count(),
            'folders' => $this->media()->folders(),
        ];
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('upload')
                ->label('Upload files')
                ->icon('heroicon-o-arrow-up-tray')
                ->modalHeading('Upload to media library')
                ->schema([
                    Forms\Components\FileUpload::make('files')
                        ->label('Images')
                        ->multiple()
                        ->image()
                        ->maxFiles(20)
                        ->maxSize(4096)
                        ->disk(PublicImageUrl::DISK)
                        ->directory(MediaLibraryService::UPLOAD_DIRECTORY)
                        ->visibility('public')
                        ->required(),
                ])
                ->action(function (array $data): void {
                    abort_unless(static::canAccess(), 403);
                    $count = count((array) ($data['files'] ?? []));
                    Notification::make()->success()->title("Uploaded {$count} file(s)")->send();
                }),
        ];
    }

    public function deleteFile(string $path): void
    {
        abort_unless(static::canAccess(), 403);

        try {
            $this->media()->delete($path);
            Notification::make()->success()->title('File deleted')->send();
        } catch (InvalidArgumentException $e) {
            Notification::make()->danger()->title('Cannot delete file')->body($e->getMessage())->send();
        }
    }
}
