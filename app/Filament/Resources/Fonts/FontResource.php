<?php

namespace App\Filament\Resources\Fonts;

use App\Filament\Resources\Fonts\Pages\ManageFonts;
use App\Models\Font;
use BackedEnum;
use Closure;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;
use UnitEnum;

// Fonts offered in the document editor. Super admins only, so it is left out of Shield's role permissions.
class FontResource extends Resource
{
    protected static ?string $model = Font::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedLanguage;

    protected static ?string $recordTitleAttribute = 'name';

    public static function canAccess(): bool
    {
        return auth()->user()?->isSuperAdmin() ?? false;
    }

    public static function getModelLabel(): string
    {
        return __('Font');
    }

    public static function getPluralModelLabel(): string
    {
        return __('Fonts');
    }

    public static function getNavigationGroup(): string|UnitEnum|null
    {
        return __('Settings');
    }

    /** Uploaded fonts get a generated key: mPDF and CSS both need a plain lowercase name. */
    public static function newFamily(): string
    {
        return 'custom'.Str::lower(Str::random(8));
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('name')
                ->helperText(__('Shown in the editor font list.'))
                ->required()
                ->maxLength(255),
            // A built-in font row has no file, an uploaded one keeps its own; the source is fixed once saved.
            FileUpload::make('file')
                ->label(__('Font file (TTF)'))
                ->helperText(__('TrueType .ttf only. For Arabic, use a font that has Arabic letters.'))
                ->disk('local')
                ->visibility('private')
                ->directory('fonts')
                ->maxSize(10240)
                ->rule(fn (): Closure => function (string $attribute, mixed $value, Closure $fail): void {
                    // mPDF reads TrueType outlines only: the file must start with 00 01 00 00 or "true".
                    if (! $value instanceof UploadedFile) {
                        return;
                    }

                    if (! in_array(file_get_contents($value->getRealPath(), length: 4), ["\x00\x01\x00\x00", 'true'], true)) {
                        $fail(__('This is not a TrueType (.ttf) font.'));

                        return;
                    }

                    // Some fonts use OpenType features mPDF cannot read (Amiri, for one), which would break every PDF using them.
                    if ($error = Font::pdfError($value->getRealPath())) {
                        $fail(__('The PDF engine cannot use this font. Try another one. (:error)', ['error' => $error]));
                    }
                })
                ->live()
                ->visible(fn (?Font $record): bool => $record === null || filled($record->file))
                ->required(fn (?Font $record): bool => filled($record?->file)),
            Select::make('family')
                ->label(__('Built-in font'))
                ->helperText(__('Or pick a font that ships with the PDF engine instead of uploading one.'))
                ->options(fn (): array => collect(Font::builtIn())->keys()->mapWithKeys(fn (string $family): array => [$family => $family])->all())
                ->searchable()
                ->unique(ignoreRecord: true)
                ->disabledOn('edit')
                ->visible(fn (?Font $record, Get $get): bool => $record ? blank($record->file) : blank($get('file')))
                ->required(fn (?Font $record, Get $get): bool => ! $record && blank($get('file'))),
        ])->columns(1);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->searchable(),
                TextColumn::make('family')
                    ->label(__('Font key')),
                TextColumn::make('file')
                    ->label(__('Source'))
                    ->formatStateUsing(fn (?string $state): string => $state ? __('Uploaded') : __('Built-in'))
                    ->placeholder(__('Built-in')),
            ])
            ->defaultSort('name')
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageFonts::route('/'),
        ];
    }
}
