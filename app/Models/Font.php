<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Mpdf\Config\ConfigVariables;
use Mpdf\Config\FontVariables;
use Mpdf\Mpdf;
use Throwable;

class Font extends Model
{
    protected $guarded = ['id'];

    /** Font sizes offered in the editor, in points. */
    public const SIZES = [8, 9, 10, 11, 12, 13, 14, 16, 18, 20, 24, 28, 32];

    /** @return array<string, string> mPDF's own fonts, family => regular TTF file name */
    public static function builtIn(): array
    {
        return collect((new FontVariables)->getDefaults()['fontdata'])
            ->filter(fn (array $font): bool => isset($font['R']))
            ->map(fn (array $font): string => $font['R'])
            ->all();
    }

    /** Why mPDF cannot render Arabic and Latin text with this TTF, or null when it can. */
    public static function pdfError(string $path): ?string
    {
        // A one-off key, so mPDF's font cache never mixes it up with a saved font.
        $key = 'fontcheck'.Str::lower(Str::random(8));

        try {
            $mpdf = new Mpdf([
                'tempDir' => storage_path('framework/cache/mpdf'),
                'fontDir' => [dirname($path)],
                'fontdata' => [$key => ['R' => basename($path), 'useOTL' => 0xFF, 'useKashida' => 75]],
                'default_font' => $key,
            ]);
            $mpdf->WriteHTML("<p style=\"font-family: {$key}\">تعزيز مبادئ الحوكمة Hello 123</p>");
            $mpdf->Output('', 'S');

            return null;
        } catch (Throwable $e) {
            return $e->getMessage();
        } finally {
            File::delete(File::glob(storage_path("framework/cache/mpdf/ttfontdata/{$key}*")));
        }
    }

    /** Absolute path of the regular TTF. */
    public function path(): ?string
    {
        if ($this->file) {
            return Storage::disk('local')->path($this->file);
        }

        $file = static::builtIn()[$this->family] ?? null;

        return $file ? (new ConfigVariables)->getDefaults()['fontDir'][0].'/'.$file : null;
    }
}
