<?php

namespace App\Filament\Resources\Documents\Pages;

use App\Filament\Resources\Documents\DocumentResource;
use App\Models\Document;
use App\Models\DocumentTemplate;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Pages\ListRecords;

class ListDocuments extends ListRecords
{
    protected static string $resource = DocumentResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('fromTemplate')
                ->label(__('New from template'))
                ->color('gray')
                // Hidden on request; switch back to DocumentResource::canCreate() to show it again.
                ->visible(false)
                ->schema([
                    Select::make('template_id')
                        ->label('Template')
                        ->options(fn () => DocumentTemplate::active()->orderBy('name')->pluck('name', 'id'))
                        ->required(),
                    TextInput::make('title')
                        ->required()
                        ->maxLength(255),
                ])
                ->action(function (array $data) {
                    $template = DocumentTemplate::active()->findOrFail($data['template_id']);

                    $document = Document::create([
                        'title' => $data['title'],
                        ...$template->only(Document::TEMPLATE_FIELDS),
                    ]);

                    $this->redirect(DocumentResource::getUrl('edit', ['record' => $document]));
                }),
            CreateAction::make(),
        ];
    }
}
