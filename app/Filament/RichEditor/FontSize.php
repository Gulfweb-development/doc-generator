<?php

namespace App\Filament\RichEditor;

use Tiptap\Core\Extension;
use Tiptap\Utils\InlineStyle;

/** Server side twin of TipTap's FontSize, which tiptap-php does not ship. */
class FontSize extends Extension
{
    public static $name = 'fontSize';

    public function addGlobalAttributes()
    {
        return [
            [
                'types' => ['textStyle'],
                'attributes' => [
                    'fontSize' => [
                        'default' => null,
                        'parseHTML' => fn ($DOMNode) => InlineStyle::getAttribute($DOMNode, 'font-size'),
                        'renderHTML' => function ($attributes) {
                            $fontSize = $attributes?->fontSize ?? null;

                            return InlineStyle::isValidCSSStyleValue($fontSize) ? ['style' => "font-size: {$fontSize}"] : null;
                        },
                    ],
                ],
            ],
        ];
    }
}
