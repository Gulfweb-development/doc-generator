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
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
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
        return __('Header / footer');
    }

    public static function getPluralModelLabel(): string
    {
        return __('Headers & footers');
    }

    public static function getNavigationGroup(): string|UnitEnum|null
    {
        return __('Documents');
    }

    private static function types(): array
    {
        return ['header' => __('Header'), 'footer' => __('Footer')];
    }

    public static function form(Schema $schema): Schema
    {
        $isHeader = fn (Get $get): bool => $get('type') === 'header';

        return $schema->components([
            TextInput::make('name')
                ->required()
                ->maxLength(255),
            Select::make('type')
                ->options(static::types())
                ->default('header')
                ->required()
                ->live(),
            DocumentContent::editor('content')
                ->columnSpanFull(),
            DocumentContent::imageUpload('logo')
                ->helperText(__('Leave empty to use the logo from General settings.'))
                ->visible($isHeader),
            Select::make('logo_position')
                ->options([
                    'left' => __('Left'),
                    'center' => __('Center'),
                    'right' => __('Right'),
                ])
                ->default('left')
                ->required()
                ->visible($isHeader),
            ColorPicker::make('accent_color')
                ->hexColor()
                ->helperText(__('Used for the page edge band, company name and table rules.'))
                ->visible($isHeader),
            Toggle::make('is_default')
                ->label('Default')
                ->helperText(__('Pre-selected on new documents and templates.')),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->searchable(),
                TextColumn::make('type')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => static::types()[$state] ?? $state),
                IconColumn::make('is_default')
                    ->label('Default')
                    ->boolean(),
            ])
            ->filters([
                SelectFilter::make('type')
                    ->options(static::types()),
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
