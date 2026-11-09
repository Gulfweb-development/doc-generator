<?php

namespace App\Filament\Resources\DocumentLayouts\Pages;

use App\Filament\Resources\DocumentLayouts\DocumentLayoutResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListDocumentLayouts extends ListRecords
{
    protected static string $resource = DocumentLayoutResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
