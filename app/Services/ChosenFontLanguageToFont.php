<?php

namespace App\Services;

use Closure;
use Mpdf\Language\LanguageToFont;
use Mpdf\Language\LanguageToFontInterface;

/**
 * mPDF swaps every Arabic (or other script) run to its own font for that script, which would undo
 * a font chosen in the editor. Keep the current font when it can draw the script itself.
 */
class ChosenFontLanguageToFont implements LanguageToFontInterface
{
    /**
     * @param  Closure(): ?string  $currentFont  mPDF's current font family
     * @param  list<string>  $capableFonts  uploaded fonts and built-in fonts made for Arabic
     */
    public function __construct(private Closure $currentFont, private array $capableFonts, private LanguageToFont $default = new LanguageToFont) {}

    public function getLanguageOptions($llcc, $adobeCJK)
    {
        [$coreSuitable, $font] = $this->default->getLanguageOptions($llcc, $adobeCJK);

        return [$coreSuitable, in_array(($this->currentFont)(), $this->capableFonts, true) ? null : $font];
    }
}
