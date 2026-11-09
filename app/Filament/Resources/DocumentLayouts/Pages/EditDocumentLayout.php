<?php

namespace App\Filament\Resources\DocumentLayouts\Pages;

use App\Filament\Resources\DocumentLayouts\DocumentLayoutResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditDocumentLayout extends EditRecord
{
    protected static string $resource = DocumentLayoutResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
