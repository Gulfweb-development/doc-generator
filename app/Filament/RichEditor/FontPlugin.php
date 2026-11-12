<?php

namespace App\Filament\RichEditor;

use App\Models\Font;
use Filament\Forms\Components\RichEditor\Plugins\Contracts\HasToolbarButtons;
use Filament\Forms\Components\RichEditor\Plugins\Contracts\RichContentPlugin;
use Filament\Support\Facades\FilamentAsset;
use Tiptap\Extensions\FontFamily;
use Tiptap\Marks\TextStyle;

/** Font and font size dropdowns for the selected text, from the fonts super admins manage. */
class FontPlugin implements HasToolbarButtons, RichContentPlugin
{
    public static function make(): static
    {
        return app(static::class);
    }

    public function getTipTapPhpExtensions(): array
    {
        return [app(TextStyle::class), app(FontFamily::class), app(FontSize::class)];
    }

    public function getTipTapJsExtensions(): array
    {
        return [FilamentAsset::getScriptSrc('rich-editor-fonts')];
    }

    public function getEditorTools(): array
    {
        return [
            SelectTool::make('fontFamily')
                ->label(__('Font'))
                ->select('fontFamily', 'setFontFamily', 'unsetFontFamily', Font::orderBy('name')->pluck('name', 'family')->all()),
            SelectTool::make('fontSize')
                ->label(__('Size'))
                ->select('fontSize', 'setFontSize', 'unsetFontSize', collect(Font::SIZES)->mapWithKeys(fn (int $size): array => ["{$size}pt" => "{$size} pt"])->all()),
        ];
    }

    public function getEditorActions(): array
    {
        return [];
    }

    public function getEnabledToolbarButtons(): array
    {
        return [['fontFamily', 'fontSize']];
    }

    public function getDisabledToolbarButtons(): array
    {
        return [];
    }
}
