<?php

namespace App\Filament\Resources\Documents\Tables;

use App\Filament\Resources\Documents\Schemas\DocumentContent;
use App\Models\Document;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class DocumentsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('title')
                    ->searchable(),
                TextColumn::make('reference')
                    ->searchable()
                    ->placeholder('-'),
                TextColumn::make('status')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => __(ucfirst($state)))
                    ->color(fn (string $state): string => $state === 'final' ? 'success' : 'gray'),
                TextColumn::make('language')
                    ->label('Document language')
                    ->formatStateUsing(fn (string $state): string => DocumentContent::languages()[$state] ?? $state),
                TextColumn::make('updated_at')
                    ->label('Updated at')
                    ->dateTime()
                    ->sortable(),
            ])
            ->defaultSort('updated_at', 'desc')
            ->filters([
                SelectFilter::make('status')
                    ->options([
                        'draft' => __('Draft'),
                        'final' => __('Final'),
                    ]),
            ])
            ->recordActions([
                static::pdfAction(),
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->checkIfRecordIsSelectableUsing(fn (Document $record): bool => ! $record->isFinal())
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()->authorizeIndividualRecords('delete'),
                ]),
            ]);
    }

    public static function pdfAction(): Action
    {
        return Action::make('pdf')
            ->label('PDF')
            ->icon(Heroicon::OutlinedDocumentArrowDown)
            ->url(fn (Document $record): string => route('filament.admin.documents.pdf', $record))
            ->openUrlInNewTab();
    }
}
