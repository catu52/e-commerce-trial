<?php

namespace App\Filament\Resources\ArticleResource\Pages;

use App\Filament\Resources\ArticleResource;
use App\Models\Article;
use App\Services\ArticlePreviewUrl;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditArticle extends EditRecord
{
    protected static string $resource = ArticleResource::class;

    private ?string $previousCover = null;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('viewOnSite')
                ->label(fn (Article $record) => $record->isLive() ? 'View on site' : 'Preview on site')
                ->icon('heroicon-o-arrow-top-right-on-square')
                ->url(fn (Article $record) => app(ArticlePreviewUrl::class)->for($record), shouldOpenInNewTab: true),
            Actions\DeleteAction::make()
                ->after(fn (Article $record) => ArticleResource::deleteCover($record->cover_image_path)),
        ];
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $this->previousCover = $this->record->cover_image_path;

        if (($data['status'] ?? null) === Article::STATUS_PUBLISHED && empty($data['published_at'])) {
            $data['published_at'] = now();
        }

        return $data;
    }

    protected function afterSave(): void
    {
        if ($this->previousCover && $this->previousCover !== $this->record->cover_image_path) {
            ArticleResource::deleteCover($this->previousCover);
        }
    }
}
