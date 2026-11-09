<?php

namespace App\Services;

use App\Models\Document;
use Illuminate\Support\Facades\Storage;
use Mpdf\HTMLParserMode;
use Mpdf\Mpdf;
use RuntimeException;
use Symfony\Component\HtmlSanitizer\HtmlSanitizer;
use Symfony\Component\HtmlSanitizer\HtmlSanitizerConfig;

class DocumentPdf
{
    public function render(Document $document): string
    {
        // Measured: about 4 ms and 50 KB per table row, so 20,000 rows need 77 s and 1 GB.
        // shortcut: renders in the web request. Move to a queued job if documents outgrow the web server timeout.
        set_time_limit(300);
        ini_set('memory_limit', '1536M');

        $mpdf = new Mpdf([
            'tempDir' => storage_path('framework/cache/mpdf'),
            'format' => 'A4',
            'default_font' => 'freeserif',
            'watermark_font' => 'freesans',
            'autoScriptToLang' => true,
            'autoLangToFont' => true,
            'margin_left' => 24,
            'margin_right' => 24,
            'margin_header' => 12,
            'margin_footer' => 10,
            // Grow page margins to fit whatever height the header and footer turn out to be.
            'setAutoTopMargin' => 'stretch',
            'setAutoBottomMargin' => 'stretch',
            'autoMarginPadding' => 6,
            // Do not enable packTableData: it saves memory but crashes on merged table cells.
        ]);

        $direction = $document->language === 'ar' ? 'rtl' : 'ltr';

        // Goes straight into CSS, so only a plain hex colour may pass.
        $accent = preg_match('/^#[0-9a-f]{6}$/i', (string) $document->header?->accent_color)
            ? $document->header->accent_color
            : '#1f3a5f';

        $mpdf->SetDirectionality($direction);

        // Binding edge band: left in English, right in Arabic, on every page. mPDF ignores
        // fixed positioning in headers, so it goes through the watermark image layer instead.
        $mpdf->imageVars['band'] = $this->solidPixel($accent);
        $mpdf->SetWatermarkImage('var:band', 1, [3.5, 297], [$direction === 'rtl' ? 206.5 : 0, 0]);
        $mpdf->showWatermarkImage = true;
        $mpdf->watermarkImgBehind = true;

        if (! $document->isFinal()) {
            $mpdf->SetWatermarkText('DRAFT', 0.08);
            $mpdf->showWatermarkText = true;
        }

        // Images only reach mPDF as bytes, so document content can never make it read a path or URL.
        foreach (['logo', 'signature', 'stamp'] as $name) {
            if ($image = $this->image($document, $name)) {
                $mpdf->imageVars[$name] = $image;
            }
        }

        $has = fn (string $name): bool => isset($mpdf->imageVars[$name]);

        $body = view('pdf.body', [
            'body' => $this->content($document, $document->body),
            'signature' => $has('signature'),
            'stamp' => $has('stamp'),
            'signatureLabel' => trans('Authorized signature', [], $document->language),
        ])->render();

        // mPDF refuses HTML longer than this limit, and the default is 1 MB.
        ini_set('pcre.backtrack_limit', (string) max(1_000_000, strlen($body) * 2));

        $mpdf->WriteHTML(view('pdf.styles', compact('accent', 'direction'))->render(), HTMLParserMode::HEADER_CSS);
        $mpdf->SetHTMLHeader(view('pdf.header', [
            'content' => $this->content($document, $document->header?->content),
            'logo' => $has('logo'),
            'position' => $document->header?->logo_position ?? 'left',
            'direction' => $direction,
        ])->render());
        $mpdf->SetHTMLFooter(view('pdf.footer', [
            'content' => $this->content($document, $document->footer?->content),
            'pageNumber' => trans('Page :x of :y', ['x' => '{PAGENO}', 'y' => '{nbpg}'], $document->language),
            'direction' => $direction,
        ])->render());
        $mpdf->WriteHTML($body, HTMLParserMode::HTML_BODY);

        return $mpdf->Output('', 'S');
    }

    private function solidPixel(string $hex): string
    {
        $pixel = imagecreatetruecolor(1, 1);
        imagefill($pixel, 0, 0, imagecolorallocate($pixel, ...sscanf($hex, '#%02x%02x%02x')));

        ob_start();
        imagepng($pixel);

        return ob_get_clean();
    }

    private function content(Document $document, ?string $html): string
    {
        if (blank($html)) {
            return '';
        }

        // Filament's shared sanitizer truncates at 500 KB, which would silently cut large documents.
        $config = app(HtmlSanitizerConfig::class)->withMaxInputLength(-1)->dropElement('img');
        $html = (new HtmlSanitizer($config))->sanitize($html);

        // The sanitizer keeps style attributes, and mPDF would fetch any url() inside them.
        $html = preg_replace('/\sstyle="[^"]*url\s*\([^"]*"/i', '', $html);

        // The editor keeps header rows in tbody, and mPDF only repeats thead on each new page.
        $html = preg_replace(
            '#<tbody>\s*(<tr[^>]*>\s*<th\b(?:(?!<td\b|</tr>).)*+</tr>)#si',
            '<thead>$1</thead><tbody>',
            $html,
        );

        // mPDF ignores block alignment inside table cells, so the cell has to carry it.
        $html = preg_replace(
            '#<(td|th)\b((?:(?!style=)[^>])*)>(\s*<(?:p|h[1-6]) style="(text-align:[^"]*)")#i',
            '<$1$2 style="$4">$3',
            $html,
        );

        return preg_replace_callback(Document::PLACEHOLDER_PATTERN, function (array $match) use ($document) {
            return e($this->placeholderValue($document, $match[1]) ?? $match[0]);
        }, $html) ?? throw new RuntimeException('Could not process placeholders');
    }

    private function placeholderValue(Document $document, string $key): ?string
    {
        return match ($key) {
            'reference' => $document->reference ?? 'DRAFT',
            'date' => ($document->finalized_at ?? now())->format('Y-m-d'),
            default => filled($document->placeholder_values[$key] ?? null) ? $document->placeholder_values[$key] : null,
        };
    }

    private function image(Document $document, string $name): ?string
    {
        [$disk, $path] = match ($document->{"{$name}_mode"}) {
            'none' => [null, null],
            'upload' => ['local', $document->{$name}],
            default => $this->defaultImage($document, $name),
        };

        if (blank($path) || ! Storage::disk($disk)->exists($path)) {
            return null;
        }

        // shortcut: only PNG and JPEG, because SVG can reference external files. Rasterize SVG logos if they are needed.
        return in_array(Storage::disk($disk)->mimeType($path), ['image/png', 'image/jpeg'], true)
            ? Storage::disk($disk)->get($path)
            : null;
    }

    private function defaultImage(Document $document, string $name): array
    {
        if ($name === 'logo' && filled($document->header?->logo)) {
            return ['local', $document->header->logo];
        }

        // The site logo is public because the panel shows it. Signature and stamp stay private.
        return [$name === 'logo' ? 'public' : 'local', setting("general.{$name}")];
    }
}
