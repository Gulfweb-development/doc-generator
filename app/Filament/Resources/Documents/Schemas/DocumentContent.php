<?php

namespace App\Filament\Resources\Documents\Schemas;

use App\Models\DocumentLayout;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Select;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;

/** Fields shared by templates and documents. */
class DocumentContent
{
    public static function languages(): array
    {
        return [
            'en' => __('English (left to right)'),
            'ar' => __('Arabic (right to left)'),
        ];
    }

    public static function components(array $afterBody = []): array
    {
        return [
            Select::make('language')
                ->label('Document language')
                ->options(fn (): array => static::languages())
                ->helperText(__('Sets the PDF page direction and the language of page numbers and the signature label. Your content is not translated.'))
                ->default('en')
                ->required(),
            Select::make('layout_id')
                ->label('Letterhead')
                ->options(fn () => DocumentLayout::options())
                ->default(fn () => DocumentLayout::defaultId()),
            static::editor('body')
                ->required()
                ->helperText(__('Use {{name}} for values to fill per document. {{reference}} and {{date}} are filled automatically.'))
                ->columnSpanFull(),
            ...$afterBody,
            Section::make(__('Logo, signature and stamp'))
                ->columns(3)
                ->columnSpanFull()
                ->schema([
                    static::image('logo'),
                    static::image('signature'),
                    static::image('stamp'),
                ]),
        ];
    }

    public static function editor(string $name): RichEditor
    {
        return RichEditor::make($name)
            // PDF images come only from the logo, signature and stamp fields.
            ->disableToolbarButtons(['attachFiles'])
            ->extraInputAttributes(['dir' => 'auto']);
    }

    public static function imageUpload(string $name): FileUpload
    {
        return FileUpload::make($name)
            ->acceptedFileTypes(['image/png', 'image/jpeg'])
            ->disk('local')
            ->visibility('private')
            ->directory('documents/images');
    }

    private static function image(string $name): Group
    {
        return Group::make([
            Radio::make("{$name}_mode")
                ->label(ucfirst($name))
                ->options([
                    'default' => __('Default'),
                    'upload' => __('Upload'),
                    'none' => __('None'),
                ])
                ->default('default')
                ->inline()
                ->inlineLabel(false)
                ->required()
                ->live(),
            static::imageUpload($name)
                ->hiddenLabel()
                ->required()
                ->visible(fn (Get $get): bool => $get("{$name}_mode") === 'upload'),
        ]);
    }
}
