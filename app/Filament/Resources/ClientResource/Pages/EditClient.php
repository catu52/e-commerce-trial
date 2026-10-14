<?php

namespace App\Filament\Resources\ClientResource\Pages;

use App\Filament\Resources\ClientResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditClient extends EditRecord
{
    protected static string $resource = ClientResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ClientResource::resetPasswordAction(),
            ClientResource::revokeSessionsAction(),
            Actions\DeleteAction::make()->hidden(fn () => ! ClientResource::canDelete($this->record)),
        ];
    }
}
