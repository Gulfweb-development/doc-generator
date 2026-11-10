<?php

namespace App\Filament\Resources\PdfDocuments\Pages;

use App\Filament\Resources\PdfDocuments\PdfDocumentResource;
use App\Models\DocumentTemplate;
use App\Models\PdfDocument;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Pages\ListRecords;

class ListPdfDocuments extends ListRecords
{
    protected static string $resource = PdfDocumentResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('fromTemplate')
                ->label(__('New from template'))
                ->color('gray')
                ->visible(fn (): bool => PdfDocumentResource::canCreate())
                ->schema([
                    Select::make('template_id')
                        ->label('Template')
                        ->options(fn () => DocumentTemplate::orderBy('name')->pluck('name', 'id'))
                        ->required(),
                    TextInput::make('title')
                        ->required()
                        ->maxLength(255),
                ])
                ->action(function (array $data) {
                    $template = DocumentTemplate::findOrFail($data['template_id']);

                    $document = PdfDocument::create([
                        'title' => $data['title'],
                        'template_id' => $template->id,
                        ...$template->only(PdfDocument::TEMPLATE_FIELDS),
                    ]);

                    $this->redirect(PdfDocumentResource::getUrl('edit', ['record' => $document]));
                }),
            CreateAction::make(),
        ];
    }
}
