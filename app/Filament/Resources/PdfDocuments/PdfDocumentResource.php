<?php

namespace App\Filament\Resources\PdfDocuments;

use App\Filament\Resources\Documents\Schemas\DocumentContent;
use App\Filament\Resources\PdfDocuments\Pages\CreatePdfDocument;
use App\Filament\Resources\PdfDocuments\Pages\EditPdfDocument;
use App\Filament\Resources\PdfDocuments\Pages\ListPdfDocuments;
use App\Models\DocumentTemplate;
use App\Models\PdfDocument;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use UnitEnum;

class PdfDocumentResource extends Resource
{
    protected static ?string $model = PdfDocument::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedDocumentText;

    protected static ?int $navigationSort = 1;

    protected static ?string $recordTitleAttribute = 'title';

    public static function getModelLabel(): string
    {
        return __('Document');
    }

    public static function getPluralModelLabel(): string
    {
        return __('Documents');
    }

    public static function getNavigationGroup(): string|UnitEnum|null
    {
        return __('Documents');
    }

    // Checked here, not in the policy, because deploys regenerate Shield policies. Finalized documents can still be deleted.
    public static function canEdit(Model $record): bool
    {
        return ! $record->isFinal() && parent::canEdit($record);
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components(static::components(
            TextInput::make('title')
                ->required()
                ->maxLength(255),
            [static::templateSelect()],
        ));
    }

    /** Fills the fields below from a template. The choice is saved, the content is copied, so later template edits do not reach the document. */
    private static function templateSelect(): Select
    {
        return Select::make('template_id')
            ->label('Template')
            ->options(fn () => DocumentTemplate::orderBy('name')->pluck('name', 'id'))
            ->placeholder(__('No template'))
            ->helperText(__('Choosing a template replaces the language, body, images, signature and stamp below.'))
            ->live()
            ->columnSpanFull()
            ->afterStateUpdated(function (?string $state, Set $set): void {
                $template = DocumentTemplate::find($state);

                if (! $template) {
                    return;
                }

                foreach (PdfDocument::TEMPLATE_FIELDS as $field) {
                    $value = $template->{$field};

                    // File uploads hold their state as a list of files keyed by id.
                    $set($field, in_array($field, PdfDocument::IMAGES, true) ? ($value ? [(string) Str::uuid() => $value] : []) : $value);
                }
            });
    }

    /** Shared with templates, which name the first field differently and have no template picker. */
    public static function components(TextInput $titleField, array $before = []): array
    {
        return [
            Section::make(__('Content'))
                ->columns(2)
                ->columnSpanFull()
                ->schema([
                    ...$before,
                    $titleField,
                    Select::make('language')
                        ->label('Document language')
                        ->options(fn (): array => DocumentContent::languages())
                        ->helperText(__('Sets the PDF page direction and the language of the signature label. Your content is not translated.'))
                        ->default('en')
                        ->required(),
                    DocumentContent::editor('body')
                        ->required()
                        ->columnSpanFull(),
                ]),
            Section::make(__('Images'))
                ->description(__('All optional. PNG or JPEG. Default signature and stamp come from General settings.'))
                ->columns(3)
                ->columnSpanFull()
                ->schema([
                    DocumentContent::imageUpload('header_image')
                        ->helperText(__('Full width at the top of every page.')),
                    DocumentContent::imageUpload('footer_image')
                        ->helperText(__('Full width at the bottom of every page.')),
                    DocumentContent::imageUpload('background_image')
                        ->helperText(__('Fills every page behind the content. Use an A4 shaped image.')),
                    // Default uses the signature and stamp from General settings.
                    DocumentContent::image('signature'),
                    DocumentContent::image('stamp'),
                ]),
        ];
    }

    public static function table(Table $table): Table
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
                    ->tooltip(fn (PdfDocument $record): ?string => $record->isFinal() ? __('Finalized documents are locked and cannot be edited.') : null)
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
                EditAction::make()
                    ->hidden(fn (PdfDocument $record): bool => $record->isFinal()),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()->authorizeIndividualRecords('delete'),
                ]),
            ]);
    }

    public static function pdfAction(string $name = 'pdf'): Action
    {
        return Action::make($name)
            ->label('PDF')
            ->icon(Heroicon::OutlinedDocumentArrowDown)
            ->url(fn (PdfDocument $record): string => route('filament.admin.pdf-documents.pdf', $record))
            ->openUrlInNewTab();
    }

    public static function getPages(): array
    {
        return [
            'index' => ListPdfDocuments::route('/'),
            'create' => CreatePdfDocument::route('/create'),
            'edit' => EditPdfDocument::route('/{record}/edit'),
        ];
    }
}
