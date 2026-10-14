<?php

namespace App\Filament\Resources\AccessTokenResource\Pages;

use App\Filament\Resources\AccessTokenResource;
use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;

class ListAccessTokens extends ListRecords
{
    protected static string $resource = AccessTokenResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('createToken')
                ->label('Create staff token')
                ->icon('heroicon-o-plus')
                ->visible(fn () => AccessTokenResource::isAdmin())
                ->schema(AccessTokenResource::formSchema())
                ->action(function (array $data): void {
                    abort_unless(AccessTokenResource::isAdmin(), 403);

                    $plain = AccessTokenResource::createTokenForStaff($data);

                    Notification::make()
                        ->title('Token created. Copy it now, it will not be shown again.')
                        ->body($plain)
                        ->success()
                        ->persistent()
                        ->send();
                }),
        ];
    }
}
