<?php

namespace App\Filament\Resources\PdfDocuments\Pages;

use App\Filament\Resources\PdfDocuments\PdfDocumentResource;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Filament\Support\Icons\Heroicon;

class EditPdfDocument extends EditRecord
{
    protected static string $resource = PdfDocumentResource::class;

    // An old link or bookmark to a finalized document explains itself instead of a 403 page.
    protected function authorizeAccess(): void
    {
        if ($this->getRecord()->isFinal()) {
            Notification::make()
                ->warning()
                ->title(__('This document is finalized'))
                ->body(__('Finalized documents are locked and cannot be edited.'))
                ->send();

            $this->redirect(static::getResource()::getUrl('index'));

            return;
        }

        parent::authorizeAccess();
    }

    protected function getHeaderActions(): array
    {
        return [
            PdfDocumentResource::pdfAction()
                ->label(__('Preview'))
                ->tooltip(__('Shows the last saved version')),
            Action::make('finalize')
                ->label(__('Finalize'))
                ->icon(Heroicon::OutlinedLockClosed)
                ->color('success')
                ->requiresConfirmation()
                ->modalDescription(__('This assigns a reference number and locks the document. It cannot be edited afterwards.'))
                ->action(function (): void {
                    // Form state may be newer than the database, and the PDF is built from the database.
                    $this->save(shouldRedirect: false, shouldSendSavedNotification: false);

                    $this->getRecord()->finalize();

                    Notification::make()
                        ->success()
                        ->title(__('Document finalized'))
                        ->body($this->getRecord()->reference)
                        ->send();

                    $this->redirect(static::getResource()::getUrl('index'));
                }),
            DeleteAction::make(),
        ];
    }
}
