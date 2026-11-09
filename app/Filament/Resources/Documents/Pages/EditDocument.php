<?php

namespace App\Filament\Resources\Documents\Pages;

use App\Filament\Resources\Documents\DocumentResource;
use App\Filament\Resources\Documents\Tables\DocumentsTable;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Filament\Support\Icons\Heroicon;

class EditDocument extends EditRecord
{
    protected static string $resource = DocumentResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DocumentsTable::pdfAction()
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

                    $missing = $this->getRecord()->missingPlaceholders();

                    if ($missing !== []) {
                        Notification::make()
                            ->danger()
                            ->title(__('Fill in all placeholders before finalizing'))
                            ->body(implode(', ', $missing))
                            ->send();

                        return;
                    }

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

    // The save may have added or removed placeholders found in the content.
    protected function afterSave(): void
    {
        $this->refreshFormData(['placeholder_values']);
    }
}
