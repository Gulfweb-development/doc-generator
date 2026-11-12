<?php

namespace App\Filament\RichEditor;

use Filament\Forms\Components\RichEditor\RichEditorTool;
use Illuminate\Support\Js;

/** A dropdown in the editor toolbar that sets one textStyle attribute, such as the font, on the selected text. */
class SelectTool extends RichEditorTool
{
    /** @var array<string, string> value => label */
    protected array $options = [];

    protected string $attribute = '';

    protected string $setCommand = '';

    protected string $unsetCommand = '';

    /** @param  array<string, string>  $options */
    public function select(string $attribute, string $setCommand, string $unsetCommand, array $options): static
    {
        $this->attribute = $attribute;
        $this->setCommand = $setCommand;
        $this->unsetCommand = $unsetCommand;
        $this->options = $options;

        return $this;
    }

    public function toEmbeddedHtml(): string
    {
        $attribute = Js::from($this->attribute)->toHtml();
        $label = e($this->getLabel());

        $options = '<option value="">'.$label.'</option>';

        foreach ($this->options as $value => $text) {
            $options .= '<option value="'.e($value).'">'.e($text).'</option>';
        }

        // Runs in the browser only: the selection stays put and no modal or server round trip is needed.
        $change = "\$event.target.value ? \$getEditor().chain().focus().{$this->setCommand}(\$event.target.value).run() : \$getEditor().chain().focus().{$this->unsetCommand}().run()";
        // Shows the value under the cursor, re-read on every editor update.
        $effect = "editorUpdatedAt; \$el.value = \$getEditor()?.getAttributes('textStyle')[{$attribute}] ?? ''";

        return <<<HTML
            <select
                data-rich-editor-tool="{$this->getName()}"
                aria-label="{$label}"
                title="{$label}"
                tabindex="-1"
                x-on:change="{$change}"
                x-effect="{$effect}"
                style="height: 2rem; max-width: 10rem; padding: 0 1.75rem 0 0.5rem; font-size: 0.8125rem; border-radius: 0.375rem; border: 1px solid color-mix(in srgb, currentColor 20%, transparent); background-color: transparent; color: inherit;"
            >{$options}</select>
            HTML;
    }
}
