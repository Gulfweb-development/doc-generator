<?php

namespace App\Filament\RichEditor;

use App\Models\Font;
use Filament\Actions\Action;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\RichEditor\EditorCommand;
use Filament\Forms\Components\RichEditor\Plugins\Contracts\HasToolbarButtons;
use Filament\Forms\Components\RichEditor\Plugins\Contracts\RichContentPlugin;
use Filament\Forms\Components\RichEditor\RichEditorTool;
use Filament\Forms\Components\Select;
use Filament\Support\Enums\Width;
use Filament\Support\Facades\FilamentAsset;
use Filament\Support\Icons\Heroicon;
use Tiptap\Extensions\FontFamily;
use Tiptap\Marks\TextStyle;

/** Font and font size for the selected text, from the fonts super admins manage. */
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
            RichEditorTool::make('font')
                ->label(__('Font'))
                ->icon(Heroicon::OutlinedLanguage)
                ->action(arguments: '{ fontFamily: $getEditor().getAttributes(\'textStyle\').fontFamily ?? null, fontSize: $getEditor().getAttributes(\'textStyle\').fontSize ?? null }'),
        ];
    }

    public function getEditorActions(): array
    {
        return [
            Action::make('font')
                ->label(__('Font'))
                ->modalHeading(__('Font'))
                ->modalWidth(Width::Medium)
                ->fillForm(fn (array $arguments): array => [
                    'fontFamily' => $arguments['fontFamily'] ?? null,
                    'fontSize' => $arguments['fontSize'] ?? null,
                ])
                ->schema([
                    Select::make('fontFamily')
                        ->label(__('Font'))
                        ->options(fn (): array => Font::orderBy('name')->pluck('name', 'family')->all())
                        ->placeholder(__('Default')),
                    Select::make('fontSize')
                        ->label(__('Size'))
                        ->options(collect(Font::SIZES)->mapWithKeys(fn (int $size): array => ["{$size}pt" => "{$size} pt"])->all())
                        ->placeholder(__('Default')),
                ])
                ->action(function (array $arguments, array $data, RichEditor $component): void {
                    $component->runCommands(
                        [
                            filled($data['fontFamily']) ? EditorCommand::make('setFontFamily', arguments: [$data['fontFamily']]) : EditorCommand::make('unsetFontFamily'),
                            filled($data['fontSize']) ? EditorCommand::make('setFontSize', arguments: [$data['fontSize']]) : EditorCommand::make('unsetFontSize'),
                        ],
                        editorSelection: $arguments['editorSelection'],
                    );
                }),
        ];
    }

    public function getEnabledToolbarButtons(): array
    {
        return ['font'];
    }

    public function getDisabledToolbarButtons(): array
    {
        return [];
    }
}
