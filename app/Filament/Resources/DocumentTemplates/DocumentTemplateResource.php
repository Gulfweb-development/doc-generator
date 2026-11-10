<?php

namespace App\Filament\Resources\DocumentTemplates;

use App\Filament\Resources\Documents\Schemas\DocumentContent;
use App\Filament\Resources\DocumentTemplates\Pages\CreateDocumentTemplate;
use App\Filament\Resources\DocumentTemplates\Pages\EditDocumentTemplate;
use App\Filament\Resources\DocumentTemplates\Pages\ListDocumentTemplates;
use App\Filament\Resources\PdfDocuments\PdfDocumentResource;
use App\Models\DocumentTemplate;
use BackedEnum;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use UnitEnum;

class DocumentTemplateResource extends Resource
{
    protected static ?string $model = DocumentTemplate::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedDocumentDuplicate;

    protected static ?int $navigationSort = 2;

    protected static ?string $recordTitleAttribute = 'name';

    public static function getModelLabel(): string
    {
        return __('Template');
    }

    public static function getPluralModelLabel(): string
    {
        return __('Templates');
    }

    public static function getNavigationGroup(): string|UnitEnum|null
    {
        return __('Documents');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components(PdfDocumentResource::components(
            TextInput::make('name')
                ->required()
                ->maxLength(255),
            withBody: false,
        ));
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->searchable(),
                TextColumn::make('language')
                    ->label('Document language')
                    ->formatStateUsing(fn (string $state): string => DocumentContent::languages()[$state] ?? $state),
                TextColumn::make('updated_at')
                    ->label('Updated at')
                    ->dateTime()
                    ->sortable(),
                ToggleColumn::make('is_active')
                    ->label(__('Active')),
            ])
            // Newest template first, then active A–Z, then inactive A–Z.
            ->defaultSort(fn (Builder $query): Builder => $query
                ->orderByRaw('id = (select max(id) from document_templates) desc')
                ->orderByDesc('is_active')
                ->orderBy('name'))
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    BulkAction::make('activate')
                        ->label(__('Activate'))
                        ->icon(Heroicon::OutlinedCheckCircle)
                        ->authorizeIndividualRecords('update')
                        ->deselectRecordsAfterCompletion()
                        ->action(fn (Collection $records) => DocumentTemplate::whereKey($records->modelKeys())->update(['is_active' => true])),
                    BulkAction::make('deactivate')
                        ->label(__('Deactivate'))
                        ->icon(Heroicon::OutlinedXCircle)
                        ->color('gray')
                        ->authorizeIndividualRecords('update')
                        ->deselectRecordsAfterCompletion()
                        ->action(fn (Collection $records) => DocumentTemplate::whereKey($records->modelKeys())->update(['is_active' => false])),
                    DeleteBulkAction::make()->authorizeIndividualRecords('delete'),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListDocumentTemplates::route('/'),
            'create' => CreateDocumentTemplate::route('/create'),
            'edit' => EditDocumentTemplate::route('/{record}/edit'),
        ];
    }
}
