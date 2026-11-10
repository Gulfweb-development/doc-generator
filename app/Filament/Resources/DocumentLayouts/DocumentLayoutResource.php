<?php

namespace App\Filament\Resources\DocumentLayouts;

use App\Filament\Resources\DocumentLayouts\Pages\CreateDocumentLayout;
use App\Filament\Resources\DocumentLayouts\Pages\EditDocumentLayout;
use App\Filament\Resources\DocumentLayouts\Pages\ListDocumentLayouts;
use App\Filament\Resources\Documents\Schemas\DocumentContent;
use App\Models\DocumentLayout;
use BackedEnum;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\ColorPicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use UnitEnum;

class DocumentLayoutResource extends Resource
{
    protected static ?string $model = DocumentLayout::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleGroup;

    protected static ?int $navigationSort = 3;

    protected static ?string $recordTitleAttribute = 'name';

    public static function getModelLabel(): string
    {
        return __('Letterhead');
    }

    public static function getPluralModelLabel(): string
    {
        return __('Letterheads');
    }

    public static function getNavigationGroup(): string|UnitEnum|null
    {
        return __('Documents');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make(__('Details'))
                ->columns(2)
                ->columnSpanFull()
                ->schema([
                    TextInput::make('name')
                        ->required()
                        ->maxLength(255),
                    Toggle::make('is_default')
                        ->label('Default')
                        ->helperText(__('Pre-selected on new documents and templates.')),
                ]),
            Section::make(__('Header'))
                ->columns(2)
                ->columnSpanFull()
                ->schema([
                    DocumentContent::imageUpload('header_image')
                        ->columnSpanFull()
                        ->helperText(__('Optional. A full-width banner printed above the logo and header text. Any of them can be left empty.')),
                    DocumentContent::editor('header_content')
                        ->label('Left content'),
                    DocumentContent::editor('header_right_content')
                        ->label('Right content')
                        ->helperText(__('Optional. Shown at the right edge of the header, for example the company name in a second language.')),
                    DocumentContent::imageUpload('logo')
                        ->helperText(__('Leave empty to use the logo from General settings, unless a header image is set.')),
                    Select::make('logo_position')
                        ->options([
                            'left' => __('Left'),
                            'center' => __('Center'),
                            'right' => __('Right'),
                        ])
                        ->default('left')
                        ->required(),
                    ColorPicker::make('accent_color')
                        ->hexColor()
                        ->helperText(__('Used for the page edge band, company name and table rules.')),
                ]),
            Section::make(__('Footer'))
                ->columnSpanFull()
                ->schema([
                    DocumentContent::imageUpload('footer_image')
                        ->helperText(__('Optional. A full-width banner printed above the footer text and page numbers.')),
                    DocumentContent::editor('footer_content')
                        ->label('Content')
                        ->helperText(__('Page numbers are added automatically.')),
                ]),
            Section::make(__('Background, signature and stamp'))
                ->description(__('All optional.'))
                ->columns(3)
                ->columnSpanFull()
                ->schema([
                    DocumentContent::imageUpload('background_image')
                        ->helperText(__('Fills every page behind the content, for example a pre-printed letterhead design. Replaces the edge band.')),
                    DocumentContent::imageUpload('signature')
                        ->helperText(__('Used by documents set to the default signature. Leave empty to use General settings.')),
                    DocumentContent::imageUpload('stamp')
                        ->helperText(__('Used by documents set to the default stamp. Leave empty to use General settings.')),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->searchable(),
                IconColumn::make('is_default')
                    ->label('Default')
                    ->boolean(),
                TextColumn::make('updated_at')
                    ->label('Updated at')
                    ->dateTime()
                    ->sortable(),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()->authorizeIndividualRecords('delete'),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListDocumentLayouts::route('/'),
            'create' => CreateDocumentLayout::route('/create'),
            'edit' => EditDocumentLayout::route('/{record}/edit'),
        ];
    }
}
