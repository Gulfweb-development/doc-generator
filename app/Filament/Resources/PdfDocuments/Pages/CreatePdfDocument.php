<?php

namespace App\Filament\Resources\PdfDocuments\Pages;

use App\Filament\Resources\PdfDocuments\PdfDocumentResource;
use App\Models\PdfDocument;
use Filament\Resources\Pages\CreateRecord;

class CreatePdfDocument extends CreateRecord
{
    protected static string $resource = PdfDocumentResource::class;

    // The template picker and images are hidden for a single assigned template, so start from its language.
    protected function afterFill(): void
    {
        if ($template = PdfDocumentResource::singleTemplate()) {
            $this->data['language'] = $template->language;
        }
    }

    // Hidden fields are not submitted, so take the template and its images from the single assigned template.
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        if (! $template = PdfDocumentResource::singleTemplate()) {
            return $data;
        }

        return [
            ...$template->only(PdfDocument::TEMPLATE_FIELDS),
            ...$data,
            'template_id' => $template->id,
        ];
    }

    // Straight to the edit page, where Preview and Finalize live.
    protected function getRedirectUrl(): string
    {
        return static::getResource()::getUrl('edit', ['record' => $this->getRecord()]);
    }
}
