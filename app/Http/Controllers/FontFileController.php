<?php

namespace App\Http\Controllers;

use App\Models\Font;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/** Font files for the editor preview, so text shows in the font the PDF will use. */
class FontFileController extends Controller
{
    public function __invoke(Font $font): BinaryFileResponse
    {
        $path = $font->path();

        abort_unless($path && is_file($path), 404);

        return response()->file($path, [
            'Content-Type' => 'font/ttf',
            'Cache-Control' => 'private, max-age=86400',
        ]);
    }
}
