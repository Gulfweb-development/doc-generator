<?php

namespace App\Filament\Resources\PdfDocuments\Pages;

use App\Filament\Resources\PdfDocuments\PdfDocumentResource;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Js;

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

    // Same buttons at the top and bottom of the form. Action names must be unique per page, hence the suffix.
    protected function getHeaderActions(): array
    {
        return [
            Action::make('saveTop')
                ->label(__('filament-panels::resources/pages/edit-record.form.actions.save.label'))
                ->action('save'),
            ...$this->documentActions('Top'),
        ];
    }

    protected function getFormActions(): array
    {
        return [
            $this->getSaveFormAction(),
            ...$this->documentActions('Bottom'),
            $this->getCancelFormAction(),
        ];
    }

    /** Returns nothing when validation fails, so the preview tab closes instead of showing stale content. */
    public function saveForPreview(): bool
    {
        $this->save(shouldRedirect: false);

        return true;
    }

    private function documentActions(string $suffix): array
    {
        return [
            Action::make("pdf{$suffix}")
                ->label(__('Preview'))
                ->icon(Heroicon::OutlinedDocumentArrowDown)
                ->color('gray')
                ->tooltip(__('Saves your changes, then opens the PDF'))
                // The tab opens during the click so popup blockers allow it, and gets the PDF once the save succeeds.
                // Single quotes only: the handler is placed inside a double-quoted HTML attribute.
                ->alpineClickHandler(fn (): string => "const tab = window.open('', '_blank'); "
                    .'$wire.saveForPreview().then((saved) => saved ? (tab.location = '
                    .Js::from(route('filament.admin.pdf-documents.pdf', $this->getRecord()))
                    .') : tab.close())'),
            Action::make("finalize{$suffix}")
                ->label(__('Finalize'))
                ->icon(Heroicon::OutlinedLockClosed)
                ->color('success')
                ->requiresConfirmation()
                ->modalHeading(__('Finalize document'))
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
            DeleteAction::make("delete{$suffix}"),
        ];
    }
}
