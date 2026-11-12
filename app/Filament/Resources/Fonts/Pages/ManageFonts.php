<?php

namespace App\Filament\Resources\Fonts\Pages;

use App\Filament\Resources\Fonts\FontResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageFonts extends ManageRecords
{
    protected static string $resource = FontResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->mutateDataUsing(fn (array $data): array => filled($data['file'] ?? null)
                    ? [...$data, 'family' => FontResource::newFamily()]
                    : $data),
        ];
    }
}
